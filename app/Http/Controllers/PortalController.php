<?php

namespace App\Http\Controllers;

use App\AttendanceSummary;
use App\Http\Requests\PortalFilterRequest;
use App\Http\Requests\PortalSettingsRequest;
use App\Models\Block;
use App\Models\Student;
use App\Models\User;
use App\Security\PasswordPolicy;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function __construct(private AttendanceSummary $attendance) {}

    public function dashboard(PortalFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->attendance->records($request->user(), $filters);
        $trend = collect();
        foreach (CarbonPeriod::create($filters['from'], $filters['to']) as $date) {
            $trend->push(['date' => $date->toDateString(), ...$this->attendance->counts($records->where('date', $date->toDateString()))]);
        }

        return view('faculty.faculty_dashboard', [
            ...$this->options($request),
            'filters' => $filters,
            'counts' => $this->attendance->counts($records),
            'studentCount' => $this->attendance->enrollments($request->user())
                ->whereHas('student', fn (Builder $query) => $query->whereNull('deleted_at'))
                ->distinct()->count('student_id'),
            'trend' => $trend,
            'rankings' => collect(['absent', 'late'])->mapWithKeys(fn (string $status): array => [
                $status => $records->where('status', $status)->groupBy('enrollment.student_id')
                    ->map(fn (Collection $rows): array => [
                        'student' => $rows->first()['enrollment']->student,
                        'count' => $rows->count(),
                    ])->sortByDesc('count')->take(5),
            ]),
            'announcements' => $this->announcements($request->user())->limit(5)->get(),
            'complaints' => $this->complaints($request->user())->where('student_complaints.status', '!=', 'resolved')
                ->limit(5)->get(),
        ]);
    }

    public function attendance(PortalFilterRequest $request, ?Student $student = null): View
    {
        if ($student) {
            abort_unless($this->attendance->enrollments($request->user(), ['student_id' => $student->id])->exists(), 404);
        }
        $filters = $request->validated();
        if ($student) {
            $filters['student_id'] = $student->id;
        }
        $records = $this->attendance->records($request->user(), $filters);

        return view('faculty.faculty_attendance', [
            ...$this->options($request),
            'filters' => $filters,
            'student' => $student,
            'counts' => $this->attendance->counts($records),
            'records' => $this->paginate($records, $request),
        ]);
    }

    public function students(PortalFilterRequest $request): View
    {
        return view('faculty.faculty_students', [
            ...$this->options($request),
            'filters' => $request->validated(),
            'enrollments' => $this->attendance->enrollments($request->user(), $request->validated())
                ->orderBy('subject_id')->orderBy('student_id')->paginate(20)->withQueryString(),
        ]);
    }

    public function schedules(PortalFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('faculty.faculty_schedules', [
            ...$this->options($request),
            'filters' => $filters,
            'schedules' => $this->attendance->subjects($request->user())
                ->with(['faculty', 'enrollments.block'])
                ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
                ->when($filters['block_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('block_id', $id)))
                ->orderByRaw("CASE day WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
                ->orderBy('start_time')->paginate(20)->withQueryString(),
        ]);
    }

    public function reports(PortalFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->attendance->records($request->user(), $filters);
        $enrollments = $this->reportEnrollments($request);

        return view('faculty.faculty_reports', [
            ...$this->options($request),
            'filters' => $filters,
            'counts' => $this->attendance->counts($records),
            'records' => $this->paginate($records, $request),
            'enrollmentCount' => (clone $enrollments)->count(),
            'enrollments' => $enrollments->orderBy('created_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function export(PortalFilterRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $type = $filters['type'] ?? 'attendance';
        $records = $type === 'attendance' ? $this->attendance->records($request->user(), $filters) : collect();
        $counts = $this->attendance->counts($records);
        $enrollments = $type === 'enrollment' ? $this->reportEnrollments($request)->orderBy('id')->lazy(200) : collect();
        $preferences = $this->attendance->preferences($request->user());
        $portalLabel = $request->user()->role === 'admin' ? 'System Admin' : 'Faculty';
        $portalPrefix = $request->user()->role === 'admin' ? 'admin' : 'faculty';

        return response()->streamDownload(function () use ($filters, $type, $records, $counts, $enrollments, $preferences, $portalLabel): void {
            $stream = fopen('php://output', 'w');
            $write = function (array $row) use ($stream): void {
                $safe = array_map(function (mixed $value): string {
                    $text = (string) $value;

                    return preg_match('/^[\s]*[=+\-@]/u', $text) ? "'".$text : $text;
                }, $row);
                fputcsv($stream, $safe, ',', '"', '');
            };
            $write([$portalLabel.' '.$type.' report', $filters['from'], $filters['to'], config('app.timezone')]);
            if ($type === 'attendance') {
                $write(['Inferred from scans within current class times; holidays and schedule history are not available.']);
                $write(['Late after minutes', $preferences['late_minutes']]);
                $write(['Present', $counts['present'], 'Absent', $counts['absent'], 'Late', $counts['late'], 'Pending', $counts['pending']]);
                $write(['Date', 'Student number', 'Student', 'Subject', 'Section', 'Status (inferred)', 'First scan']);
                foreach ($records as $record) {
                    $enrollment = $record['enrollment'];
                    $write([$record['date'], $enrollment->student->student_number, $enrollment->student->full_name,
                        $enrollment->subject->code, $enrollment->block?->name, $record['status'], $record['scanned_at']?->format('Y-m-d H:i:s')]);
                }
            } else {
                $write(['Date filter applies to enrollment creation date.']);
                $write(['Enrolled on', 'Student number', 'Student', 'Subject', 'Section', 'Faculty', 'Day', 'Start', 'End', 'Room']);
                foreach ($enrollments as $enrollment) {
                    $subject = $enrollment->subject;
                    $write([$enrollment->created_at->toDateString(), $enrollment->student?->student_number,
                        $enrollment->student?->full_name, $subject->code, $enrollment->block?->name,
                        $subject->faculty?->name, $subject->day, $subject->start_time->format('H:i'),
                        $subject->end_time->format('H:i'), $subject->room]);
                }
            }
            fclose($stream);
        }, $portalPrefix.'-'.$type.'-'.$filters['from'].'-'.$filters['to'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function settings(Request $request): View
    {
        return view('faculty.faculty_settings', [
            'preferences' => $this->attendance->preferences($request->user()),
        ]);
    }

    public function updateSettings(PortalSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data): void {
            $user = $request->user();
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $password = $data['password'] ?? null;
            unset($data['password'], $data['password_confirmation']);
            $user->save();
            if ($password) {
                PasswordPolicy::change($user, $password);
            }
            DB::table('faculty_preferences')->updateOrInsert(['user_id' => $user->id], [
                'late_minutes' => $data['late_minutes'],
                'report_days' => $data['report_days'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Account and Faculty preferences saved.');
    }

    public function inbox(Request $request): View
    {
        return view('faculty.faculty_inbox', [
            ...$this->options($request),
            'announcements' => $this->announcements($request->user())->paginate(10, ['*'], 'announcements_page')->withQueryString(),
            'complaints' => $this->complaints($request->user())->paginate(10, ['*'], 'complaints_page')->withQueryString(),
        ]);
    }

    public function publishAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        abort_unless($this->attendance->subjects($request->user())->whereKey($data['subject_id'])->exists(), 404);
        DB::table('portal_announcements')->insert([
            ...$data, 'audience' => 'subject', 'author_id' => $request->user()->id,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Subject announcement published.');
    }

    public function recordComplaint(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        abort_unless($this->attendance->enrollments($request->user(), $data)->exists(), 404);
        DB::table('student_complaints')->insert([
            ...$data, 'recorded_by' => $request->user()->id,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Received student complaint recorded.');
    }

    public function updateComplaint(Request $request, int $complaint): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_review', 'resolved'])]]);
        abort_unless($this->complaints($request->user())->where('student_complaints.id', $complaint)->exists(), 404);
        DB::table('student_complaints')->where('id', $complaint)->update([...$data, 'updated_at' => now()]);

        return back()->with('success', 'Complaint status updated.');
    }

    /** @return array{subjects: Collection, students: Collection, blocks: Collection, preferences: array} */
    private function options(Request $request): array
    {
        $user = $request->user();

        return [
            'subjects' => $this->attendance->subjects($user)->orderBy('code')->get(),
            'students' => Student::query()->whereHas('subjects', fn (Builder $query) => $query->where('faculty_id', $user->id))
                ->orderBy('last_name')->orderBy('first_name')->get(),
            'blocks' => Block::query()->whereIn('id', $this->attendance->enrollments($user)->select('block_id'))
                ->orderBy('name')->get(),
            'preferences' => $this->attendance->preferences($user),
        ];
    }

    private function announcements(User $faculty): QueryBuilder
    {
        return DB::table('portal_announcements')->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
            ->join('users', 'users.id', '=', 'portal_announcements.author_id')
            ->whereNull('portal_announcements.archived_at')
            ->where(fn (QueryBuilder $query) => $query->whereNull('portal_announcements.expires_at')->orWhere('portal_announcements.expires_at', '>', now()))
            ->where(function (QueryBuilder $query) use ($faculty): void {
                $query->whereIn('portal_announcements.audience', ['all', 'faculty'])
                    ->orWhere(fn (QueryBuilder $subject) => $subject->where('portal_announcements.audience', 'subject')->where('subjects.faculty_id', $faculty->id));
            })
            ->where('published_at', '<=', now())
            ->select('portal_announcements.*', 'subjects.code as subject_code', 'users.name as author_name')
            ->orderByDesc('published_at')->orderByDesc('portal_announcements.id');
    }

    private function complaints(User $faculty): QueryBuilder
    {
        return DB::table('student_complaints')->join('subjects', 'subjects.id', '=', 'student_complaints.subject_id')
            ->join('students', 'students.id', '=', 'student_complaints.student_id')
            ->where('subjects.faculty_id', $faculty->id)
            ->select('student_complaints.*', 'subjects.code as subject_code', 'students.first_name', 'students.last_name', 'students.student_number')
            ->orderByRaw("CASE student_complaints.status WHEN 'open' THEN 1 WHEN 'in_review' THEN 2 ELSE 3 END")
            ->orderByDesc('student_complaints.created_at');
    }

    private function reportEnrollments(PortalFilterRequest $request): Builder
    {
        $filters = $request->validated();

        return $this->attendance->enrollments($request->user(), $filters)
            ->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to']);
    }

    private function paginate(Collection $records, Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return new LengthAwarePaginator($records->forPage($page, 20)->values(), $records->count(), 20, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
    }
}
