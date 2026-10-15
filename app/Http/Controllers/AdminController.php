<?php

namespace App\Http\Controllers;

use App\AttendanceSummary;
use App\Http\Requests\AdminFilterRequest;
use App\Http\Requests\AdminUserRequest;
use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\ScheduleConflicts;
use App\Security\PasswordPolicy;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct(private AttendanceSummary $attendance) {}

    public function dashboard(AdminFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->attendance->records($request->user(), $filters);
        $today = now()->toDateString();
        $todayRecords = $this->attendance->records($request->user(), ['from' => $today, 'to' => $today]);
        $trend = collect();
        foreach (CarbonPeriod::create($filters['from'], $filters['to']) as $day) {
            $trend->push(['date' => $day->toDateString(), ...$this->attendance->counts($records->where('date', $day->toDateString()))]);
        }

        return view('admin.dashboard', [
            ...$this->options($request), 'filters' => $filters,
            'studentCount' => Student::count(), 'counts' => $this->attendance->counts($todayRecords),
            'trend' => $trend, 'rankings' => $this->rankings($records),
            'announcements' => $this->announcements()->where('published_at', '<=', now())->limit(5)->get(),
            'complaints' => $this->complaints()->where('student_complaints.status', '!=', 'resolved')->limit(5)->get(),
        ]);
    }

    public function attendance(AdminFilterRequest $request, ?Student $student = null): View
    {
        $filters = $request->validated();
        if ($student) {
            $filters['student_id'] = $student->id;
        }
        $records = $this->attendance->records($request->user(), $filters);

        return view('admin.attendance', [
            ...$this->options($request), 'filters' => $filters, 'student' => $student,
            'records' => $this->paginate($records, $request), 'counts' => $this->attendance->counts($records),
        ]);
    }

    public function students(AdminFilterRequest $request): View
    {
        $filters = $request->validated();
        $students = Student::with('block')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('student_number', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%');
            }))
            ->when($filters['block_id'] ?? null, fn (Builder $query, $id) => $query->where('block_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('subjects', fn (Builder $subjects) => $subjects->whereKey($id)))
            ->orderBy('last_name')->orderBy('first_name')->paginate(20)->withQueryString();

        return view('admin.students', [...$this->options($request), 'filters' => $filters, 'directory' => $students]);
    }

    public function student(Request $request, Student $student): View
    {
        $filters = [
            'from' => now()->subDays($this->attendance->preferences($request->user())['report_days'] - 1)->toDateString(),
            'to' => now()->toDateString(),
            'student_id' => $student->id,
        ];
        $attendanceRecords = $this->attendance->records($request->user(), $filters);

        return view('admin.student', [
            'student' => $student->load('block'),
            'enrollments' => $this->attendance->enrollments($request->user(), ['student_id' => $student->id])->orderBy('subject_id')->paginate(20),
            'attendanceRecords' => $this->paginate($attendanceRecords, $request),
            'attendanceCounts' => $this->attendance->counts($attendanceRecords),
            'attendanceFilters' => $filters,
            'portalPrefix' => 'admin',
        ]);
    }

    public function schedules(AdminFilterRequest $request, ScheduleConflicts $conflicts): View
    {
        $filters = $request->validated();
        $rows = $conflicts->rows()
            ->when($filters['block_id'] ?? null, fn (Collection $rows, $id) => $rows->filter(fn (array $row): bool => array_key_exists($id, $row['sections'])))
            ->when($filters['subject_id'] ?? null, fn (Collection $rows, $id) => $rows->where('subject_id', $id))
            ->when($filters['faculty_id'] ?? null, fn (Collection $rows, $id) => $rows->where('faculty_id', $id));

        return view('admin.schedules', [...$this->options($request), 'filters' => $filters, 'schedules' => $this->paginate($rows->values(), $request)]);
    }

    public function reports(AdminFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->attendance->records($request->user(), $filters);
        $enrollments = $this->attendance->enrollments($request->user(), $filters)
            ->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to']);

        return view('admin.reports', [
            ...$this->options($request), 'filters' => $filters,
            'records' => $this->paginate($records, $request), 'counts' => $this->attendance->counts($records),
            'rankings' => $this->rankings($records), 'enrollmentCount' => (clone $enrollments)->count(),
            'enrollments' => $enrollments->orderBy('created_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function export(AdminFilterRequest $request, PortalController $portal): StreamedResponse
    {
        return $portal->export($request);
    }

    public function inbox(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'program_head'], true), 403);
        $filters = $request->validate([
            'complaint_status' => ['nullable', Rule::in(['open', 'in_review', 'resolved'])],
        ]);

        return view('admin.inbox', [
            'subjects' => Subject::orderBy('code')->get(),
            'announcements' => $this->announcements()->paginate(10, ['*'], 'announcements_page')->withQueryString(),
            'complaints' => $this->complaints()->when($filters['complaint_status'] ?? null,
                fn (QueryBuilder $query, string $status) => $query->where('student_complaints.status', $status))
                ->paginate(10, ['*'], 'complaints_page')->withQueryString(),
        ]);
    }

    public function announcement(int $announcement): View
    {
        return view('admin.message', ['kind' => 'announcement', 'record' => $this->announcements()->where('portal_announcements.id', $announcement)->firstOrFail()]);
    }

    public function complaint(int $complaint): View
    {
        return view('admin.message', ['kind' => 'complaint', 'record' => $this->complaints()->where('student_complaints.id', $complaint)->firstOrFail()]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:5000'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);
        $id = DB::table('portal_announcements')->insertGetId([
            ...$data, 'audience' => empty($data['subject_id']) ? 'all' : 'subject', 'author_id' => $request->user()->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.announcements.show', $id)->with('success', 'Announcement published.');
    }

    public function updateComplaint(Request $request, int $complaint): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_review', 'resolved'])]]);
        abort_unless(DB::table('student_complaints')->where('id', $complaint)->exists(), 404);
        DB::table('student_complaints')->where('id', $complaint)->update([...$data, 'updated_at' => now()]);

        return back()->with('success', 'Complaint status saved.');
    }

    public function users(AdminFilterRequest $request): View
    {
        $filters = $request->validated();
        $accounts = User::with('student')
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('is_active', $filters['active']))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.users', ['accounts' => $accounts, 'filters' => $filters]);
    }

    public function createUser(): View
    {
        return view('admin.user_form', ['account' => new User(['role' => 'faculty', 'is_active' => true]), 'students' => Student::orderBy('last_name')->get()]);
    }

    public function editUser(User $account): View
    {
        return view('admin.user_form', ['account' => $account, 'students' => Student::orderBy('last_name')->get()]);
    }

    public function storeUser(AdminUserRequest $request): RedirectResponse
    {
        User::create($request->safe()->except('password_confirmation'));

        return redirect()->route('admin.users')->with('success', 'Account created.');
    }

    public function updateUser(AdminUserRequest $request, User $account): RedirectResponse
    {
        $data = $request->safe()->except('password_confirmation');
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($request, $account, $data): void {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $target = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($target->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own System Admin access.']);
            }
            if ($target->role === 'admin' && ($data['role'] !== 'admin' || ! $data['is_active'])
                && $admins->where('is_active', true)->where('id', '!=', $target->id)->isEmpty()) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active System Admin.']);
            }
            if ($data['role'] !== 'faculty' && $target->assignedSubjects()->exists()) {
                throw ValidationException::withMessages(['role' => 'Reassign this faculty member’s subjects before changing their role.']);
            }
            $newPassword = $data['password'] ?? null;
            unset($data['password']);
            $target->fill([...$data, 'student_id' => $data['role'] === 'student' ? $data['student_id'] : null]);
            if ($target->isDirty('email')) {
                $target->email_verified_at = null;
            }
            if ($newPassword || $target->isDirty(['role', 'is_active'])) {
                $target->remember_token = Str::random(60);
                $target->tokens()->delete();
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }
            $target->save();
            if ($newPassword) {
                PasswordPolicy::change($target, $newPassword);
            }
        });

        return redirect()->route('admin.users')->with('success', 'Account updated.');
    }

    public function settings(Request $request): View
    {
        return view('admin.settings', ['settings' => DB::table('system_settings')->where('id', 1)->first(), 'preferences' => $this->attendance->preferences($request->user())]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', Rule::in(['First semester', 'Second semester', 'Summer'])],
            'late_minutes' => ['required', 'integer', 'between:0,60'],
            'report_days' => ['required', 'integer', Rule::in([7, 30, 60, 90])],
        ]);
        if (! empty($data['school_year']) && (int) substr($data['school_year'], 5) !== (int) substr($data['school_year'], 0, 4) + 1) {
            throw ValidationException::withMessages(['school_year' => 'Use consecutive years, for example 2026-2027.']);
        }
        DB::table('system_settings')->updateOrInsert(['id' => 1], [...$data, 'updated_at' => now()]);

        return back()->with('success', 'System settings saved.');
    }

    /** @return array<string, mixed> */
    private function options(Request $request): array
    {
        return [
            'portalPrefix' => 'admin', 'subjects' => Subject::orderBy('code')->get(),
            'students' => Student::orderBy('last_name')->orderBy('first_name')->get(),
            'blocks' => Block::orderBy('name')->get(),
            'faculty' => User::where('role', 'faculty')->orderBy('name')->get(),
            'preferences' => $this->attendance->preferences($request->user()),
        ];
    }

    private function announcements(): QueryBuilder
    {
        return DB::table('portal_announcements')->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
            ->join('users', 'users.id', '=', 'portal_announcements.author_id')
            ->select('portal_announcements.*', 'subjects.code as subject_code', 'users.name as author_name')
            ->orderByDesc('published_at')->orderByDesc('portal_announcements.id');
    }

    private function complaints(): QueryBuilder
    {
        return DB::table('student_complaints')->join('subjects', 'subjects.id', '=', 'student_complaints.subject_id')
            ->join('students', 'students.id', '=', 'student_complaints.student_id')
            ->select('student_complaints.*', 'subjects.code as subject_code', 'students.first_name', 'students.last_name', 'students.student_number')
            ->orderByRaw("CASE student_complaints.status WHEN 'open' THEN 1 WHEN 'in_review' THEN 2 ELSE 3 END")
            ->orderByDesc('student_complaints.created_at');
    }

    private function rankings(Collection $records): Collection
    {
        return $records->groupBy('enrollment.student_id')->map(fn (Collection $rows): array => [
            'student' => $rows->first()['enrollment']->student,
            'absent' => $rows->where('status', 'absent')->count(),
            'late' => $rows->where('status', 'late')->count(),
        ])->filter(fn (array $row): bool => $row['absent'] + $row['late'] > 0);
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
