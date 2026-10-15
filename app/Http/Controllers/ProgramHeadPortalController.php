<?php

namespace App\Http\Controllers;

use App\AttendanceSummary;
use App\Http\Requests\ProgramHeadFilterRequest;
use App\Http\Requests\ProgramHeadSettingsRequest;
use App\Models\Block;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Security\PasswordPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramHeadPortalController extends Controller
{
    public function __construct(private AttendanceSummary $attendance) {}

    public function reports(ProgramHeadFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->attendanceRecords($request, $filters);

        return view('program_head.reports', [
            ...$this->reportOptions(),
            'filters' => $filters,
            'counts' => $this->attendance->counts($records),
            'dailyRecords' => $records->groupBy('date'),
            'enrollments' => $this->enrollments($filters)->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'facultyLoads' => $this->facultyLoads($filters)->get(),
            'unassignedSubjects' => Subject::whereNull('faculty_id')->orderBy('code')->get(),
        ]);
    }

    public function export(ProgramHeadFilterRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $type = $filters['type'] ?? 'attendance';

        return response()->streamDownload(function () use ($request, $filters, $type): void {
            $stream = fopen('php://output', 'w');
            $write = function (array $row) use ($stream): void {
                fputcsv($stream, array_map(fn (mixed $value): string => preg_match('/^[\s]*[=+\-@]/u', (string) $value) ? "'".$value : (string) $value, $row), ',', '"', '');
            };
            $write(['Program Head '.str_replace('_', ' ', $type).' report', $filters['from'], $filters['to'], config('app.timezone')]);

            if ($type === 'attendance') {
                $write(['Date', 'Student number', 'Student', 'Subject', 'Block', 'Faculty', 'Status', 'First scan']);
                foreach ($this->attendanceRecords($request, $filters) as $record) {
                    $enrollment = $record['enrollment'];
                    $write([$record['date'], $enrollment->student?->student_number, $enrollment->student?->full_name, $enrollment->subject->code, $enrollment->block?->name, $enrollment->subject->faculty?->name, $record['status'], $record['scanned_at']?->format('Y-m-d H:i:s')]);
                }
            } elseif ($type === 'enrollment') {
                $write(['Enrolled on', 'Student number', 'Student', 'Subject', 'Block', 'Faculty']);
                foreach ($this->enrollments($filters)->orderBy('id')->lazy(200) as $enrollment) {
                    $write([$enrollment->created_at->toDateString(), $enrollment->student?->student_number, $enrollment->student?->full_name, $enrollment->subject->code, $enrollment->block?->name, $enrollment->subject->faculty?->name]);
                }
            } else {
                $write(['Faculty', 'Email', 'Subject', 'Block count', 'Students', 'Day', 'Time', 'Room']);
                foreach ($this->facultyLoads($filters)->get() as $subject) {
                    $write([$subject->faculty?->name, $subject->faculty?->email, $subject->code, $subject->enrollments->pluck('block_id')->unique()->count(), $subject->enrollments->pluck('student_id')->unique()->count(), $subject->day, $subject->start_time->format('H:i').' - '.$subject->end_time->format('H:i'), $subject->room]);
                }
            }
            fclose($stream);
        }, 'program-head-'.$type.'-'.$filters['from'].'-'.$filters['to'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function announcements(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['active', 'expired', 'archived'])]])['status'] ?? 'active';
        $query = DB::table('portal_announcements')
            ->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
            ->leftJoin('blocks', 'blocks.id', '=', 'portal_announcements.block_id')
            ->join('users', 'users.id', '=', 'portal_announcements.author_id')
            ->select('portal_announcements.*', 'subjects.code as subject_code', 'blocks.name as block_name', 'users.name as author_name')
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->when($status === 'expired', fn ($query) => $query->whereNull('archived_at')->where('expires_at', '<=', now()))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->orderByDesc('published_at');

        return view('program_head.announcements', [
            'announcements' => $query->paginate(15)->withQueryString(),
            'subjects' => Subject::orderBy('code')->get(),
            'blocks' => Block::orderBy('name')->get(),
            'status' => $status,
        ]);
    }

    public function publishAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'faculty', 'students', 'subject', 'block'])],
            'subject_id' => ['nullable', 'required_if:audience,subject', 'integer', 'exists:subjects,id'],
            'block_id' => ['nullable', 'required_if:audience,block', 'integer', 'exists:blocks,id'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $data['subject_id'] = $data['audience'] === 'subject' ? ($data['subject_id'] ?? null) : null;
        $data['block_id'] = $data['audience'] === 'block' ? ($data['block_id'] ?? null) : null;
        DB::table('portal_announcements')->insert([
            ...$data, 'author_id' => $request->user()->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Announcement published to the selected audience.');
    }

    public function archiveAnnouncement(Request $request, int $announcement): RedirectResponse
    {
        $updated = DB::table('portal_announcements')->where('id', $announcement)->where('author_id', $request->user()->id)->update(['archived_at' => now(), 'updated_at' => now()]);
        abort_unless($updated, 404);

        return back()->with('success', 'Announcement archived.');
    }

    public function settings(Request $request): View
    {
        return view('program_head.settings', [
            'preferences' => DB::table('program_head_preferences')->where('user_id', $request->user()->id)->first(),
            'system' => DB::table('system_settings')->where('id', 1)->first(),
        ]);
    }

    public function updateSettings(ProgramHeadSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data): void {
            $user = $request->user();
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            if (! empty($data['password'])) {
                PasswordPolicy::change($user, $data['password']);
            }
            DB::table('program_head_preferences')->updateOrInsert(['user_id' => $user->id], [
                'school_year' => $data['school_year'] ?? null,
                'semester' => $data['semester'] ?? null,
                'report_days' => $data['report_days'],
                'notify_announcements' => $request->boolean('notify_announcements'),
                'notify_complaints' => $request->boolean('notify_complaints'),
                'notify_schedule_changes' => $request->boolean('notify_schedule_changes'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Program Head settings saved.');
    }

    /** @param array<string, mixed> $filters */
    private function enrollments(array $filters): Builder
    {
        return Enrollment::with(['student', 'subject.faculty', 'block'])
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['block_id'] ?? null, fn (Builder $query, $id) => $query->where('block_id', $id))
            ->when($filters['faculty_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('subject', fn (Builder $subject) => $subject->where('faculty_id', $id)))
            ->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to']);
    }

    /** @param array<string, mixed> $filters */
    private function attendanceRecords(Request $request, array $filters): Collection
    {
        return $this->attendance->records($request->user(), $filters)
            ->when($filters['faculty_id'] ?? null, fn ($records, $facultyId) => $records->filter(
                fn (array $record): bool => $record['enrollment']->subject->faculty_id === (int) $facultyId
            )->values());
    }

    /** @param array<string, mixed> $filters */
    private function facultyLoads(array $filters): Builder
    {
        return Subject::with(['faculty', 'enrollments.block'])
            ->whereNotNull('faculty_id')
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->when($filters['block_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('block_id', $id)))
            ->when($filters['faculty_id'] ?? null, fn (Builder $query, $id) => $query->where('faculty_id', $id))
            ->orderBy('faculty_id')->orderBy('code');
    }

    /** @return array{subjects: mixed, blocks: mixed, faculty: mixed} */
    private function reportOptions(): array
    {
        return [
            'subjects' => Subject::orderBy('code')->get(),
            'blocks' => Block::orderBy('name')->get(),
            'faculty' => User::where('role', 'faculty')->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
