<?php

namespace App;

use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceSummary
{
    public function subjects(User $faculty): Builder
    {
        return Subject::query()->where('faculty_id', $faculty->id);
    }

    /** @param array{student_id?: int|string|null, subject_id?: int|string|null, block_id?: int|string|null} $filters */
    public function enrollments(User $faculty, array $filters = []): Builder
    {
        return Enrollment::query()
            ->whereHas('subject', fn (Builder $query) => $query->where('faculty_id', $faculty->id))
            ->with(['student', 'subject.faculty', 'block'])
            ->when($filters['student_id'] ?? null, fn (Builder $query, $id) => $query->where('student_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['block_id'] ?? null, fn (Builder $query, $id) => $query->where('block_id', $id));
    }

    /** @return array{late_minutes: int, report_days: int} */
    public function preferences(User $faculty): array
    {
        $preferences = DB::table('faculty_preferences')->where('user_id', $faculty->id)->first();

        return [
            'late_minutes' => (int) ($preferences->late_minutes ?? 15),
            'report_days' => (int) ($preferences->report_days ?? 30),
        ];
    }

    /**
     * Scans have no subject identifier; estimates use current weekly schedules.
     * Duplicate scans count once per class. No historical roll call is overwritten.
     *
     * @param  array{from: string, to: string, student_id?: int|string|null, subject_id?: int|string|null, block_id?: int|string|null, status?: string|null}  $filters
     * @return Collection<int, array{enrollment: Enrollment, date: string, status: string, scanned_at: ?CarbonImmutable}>
     */
    public function records(User $faculty, array $filters): Collection
    {
        $enrollments = $this->enrollments($faculty, $filters)->get();
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $now = CarbonImmutable::now();
        $lateMinutes = $this->preferences($faculty)['late_minutes'];
        $scans = DB::table('fingerprint_attendance_events')
            ->whereIn('student_id', $enrollments->pluck('student.student_number')->filter()->unique())
            ->whereBetween('scanned_at', [$from, $to->min($now)])
            ->orderBy('scanned_at')->get()
            ->groupBy(fn (object $scan): string => $scan->student_id.'|'.CarbonImmutable::parse($scan->scanned_at)->toDateString());
        $records = collect();

        foreach (CarbonPeriod::create($from, $to) as $date) {
            foreach ($enrollments as $enrollment) {
                $subject = $enrollment->subject;
                $student = $enrollment->student;
                if (! $student || $subject->day !== $date->format('l')) {
                    continue;
                }

                $start = CarbonImmutable::parse($date->toDateString().' '.$subject->start_time->format('H:i:s'));
                $end = CarbonImmutable::parse($date->toDateString().' '.$subject->end_time->format('H:i:s'));
                if ($end->lessThanOrEqualTo($start) || $start->isAfter($now)
                    || $enrollment->created_at->isAfter($start)
                    || ($student->deleted_at && $student->deleted_at->lessThanOrEqualTo($start))) {
                    continue;
                }

                $scan = $scans->get($student->student_number.'|'.$date->toDateString(), collect())
                    ->first(function (object $event) use ($start, $end): bool {
                        $time = CarbonImmutable::parse($event->scanned_at);

                        return $time->greaterThanOrEqualTo($start) && $time->lessThan($end);
                    });
                $scannedAt = $scan ? CarbonImmutable::parse($scan->scanned_at) : null;
                $status = $scannedAt
                    ? ($scannedAt->isAfter($start->addMinutes($lateMinutes)) ? 'late' : 'present')
                    : ($end->lessThanOrEqualTo($now) ? 'absent' : 'pending');

                if (empty($filters['status']) || $filters['status'] === $status) {
                    $records->push([
                        'enrollment' => $enrollment,
                        'date' => $date->toDateString(),
                        'status' => $status,
                        'scanned_at' => $scannedAt,
                    ]);
                }
            }
        }

        return $records->sortByDesc('date')->values();
    }

    /** @return array{present: int, absent: int, late: int, pending: int} */
    public function counts(Collection $records): array
    {
        return collect(['present', 'absent', 'late', 'pending'])
            ->mapWithKeys(fn (string $status): array => [$status => $records->where('status', $status)->count()])
            ->all();
    }
}
