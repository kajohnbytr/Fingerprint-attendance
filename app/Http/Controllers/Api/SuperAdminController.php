<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json(['settings' => $this->settingsRow()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schoolName' => ['required', 'string', 'max:150'],
            'attendanceRequired' => ['required', 'integer', 'between:1,100'],
            'proxyDetection' => ['required', 'boolean'],
            'requireAdminMfa' => ['required', 'boolean'],
            'systemLockdown' => ['required', 'boolean'],
            'sessionTimeoutMinutes' => ['required', 'integer', 'between:5,1440'],
            'passwordExpiryDays' => ['required', 'integer', 'between:0,365'],
            'lowAttendanceAlerts' => ['required', 'boolean'],
            'systemHealthReports' => ['required', 'boolean'],
        ]);

        DB::table('super_admin_settings')->updateOrInsert(['id' => 1], [
            'school_name' => $data['schoolName'],
            'attendance_required' => $data['attendanceRequired'],
            'proxy_detection' => $data['proxyDetection'],
            'require_admin_mfa' => $data['requireAdminMfa'],
            'system_lockdown' => $data['systemLockdown'],
            'session_timeout_minutes' => $data['sessionTimeoutMinutes'],
            'password_expiry_days' => $data['passwordExpiryDays'],
            'low_attendance_alerts' => $data['lowAttendanceAlerts'],
            'system_health_reports' => $data['systemHealthReports'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'School settings updated', 'Updated school, attendance, security, and notification settings.');

        return response()->json([
            'message' => 'School portal settings saved.',
            'settings' => $this->settingsRow(),
        ]);
    }

    public function masterSchedule(Request $request): JsonResponse
    {
        $entries = DB::table('master_schedule_entries')
            ->when($request->string('block')->isNotEmpty(), fn ($query) => $query->where('block', 'like', '%'.$request->string('block').'%'))
            ->orderBy('sort_order')->orderBy('block')->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id, 'block' => $row->block, 'courseName' => $row->course_name,
                'schedule' => $row->schedule, 'room' => $row->room, 'assignedProfessorEmail' => $row->assigned_professor_email,
                'graceOverrideMinutes' => $row->grace_override_minutes, 'order' => $row->sort_order,
            ]);

        return response()->json(['entries' => $entries]);
    }

    public function storeMasterSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'block' => ['required', 'string', 'max:50'], 'courseName' => ['required', 'string', 'max:255'],
            'schedule' => ['required', 'string', 'max:255'], 'room' => ['nullable', 'string', 'max:100'],
            'assignedProfessorEmail' => ['nullable', 'email', 'max:255'],
            'graceOverrideMinutes' => ['nullable', 'integer', 'between:0,180'], 'order' => ['nullable', 'integer'],
        ]);
        $id = DB::table('master_schedule_entries')->insertGetId([
            'block' => $data['block'], 'course_name' => $data['courseName'], 'schedule' => $data['schedule'],
            'room' => $data['room'] ?? null, 'assigned_professor_email' => $data['assignedProfessorEmail'] ?? null,
            'grace_override_minutes' => $data['graceOverrideMinutes'] ?? null, 'sort_order' => $data['order'] ?? 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($request, 'Master schedule created', 'Created entry '.$id.'.');

        return response()->json(['message' => 'Master schedule entry created.', 'id' => $id], 201);
    }

    public function syncMasterSchedule(Request $request): JsonResponse
    {
        $this->audit($request, 'Master schedule synchronized', 'Requested schedule synchronization.');

        return response()->json(['message' => 'Master schedule synchronized.']);
    }

    public function destroyMasterSchedule(Request $request, int $entry): JsonResponse
    {
        abort_unless(DB::table('master_schedule_entries')->where('id', $entry)->delete() > 0, 404, 'Schedule entry not found.');
        $this->audit($request, 'Master schedule deleted', 'Deleted entry '.$entry.'.');

        return response()->json(['message' => 'Master schedule entry deleted.']);
    }

    public function users(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['student', 'admin', 'program_head', 'super_admin'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()->with(['assignedSubjects.enrollments.block', 'student'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->when(filled($filters['role'] ?? null), function ($query) use ($filters): void {
                $query->where('role', $filters['role'] === 'admin' ? 'faculty' : $filters['role']);
            })
            ->when(filled($filters['status'] ?? null), function ($query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('name')->get()->map(fn (User $user): array => $this->userRow($user));

        return response()->json(['users' => $users]);
    }

    public function availableBlocks(): JsonResponse
    {
        return response()->json(['blocks' => DB::table('blocks')->orderBy('name')->pluck('name')]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $this->validateUser($request, null, true);
        $user = User::create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'],
            'role' => $data['role'] === 'admin' ? 'faculty' : $data['role'], 'is_active' => true, 'must_change_password' => true,
        ]);
        $this->audit($request, 'User created', 'Created '.$user->email.'.');

        return response()->json(['user' => $this->userRow($user)], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $this->validateUser($request, $user->id, false);
        $user->update(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'] === 'admin' ? 'faculty' : $data['role']]);
        $this->audit($request, 'User updated', 'Updated '.$user->email.'.');

        return response()->json(['user' => $this->userRow($user->fresh())]);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['isActive' => ['required', 'boolean']]);
        abort_if($request->user()->is($user) && ! $data['isActive'], 422, 'You cannot archive your own account.');

        $user->update(['is_active' => $data['isActive']]);
        $action = $data['isActive'] ? 'User reactivated' : 'User archived';
        $this->audit($request, $action, $action.' account '.$user->email.'.');

        return response()->json([
            'message' => $data['isActive'] ? 'User reactivated.' : 'User archived.',
            'user' => $this->userRow($user->fresh()),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('audit_logs.action', 'like', '%'.$request->string('search').'%'))
            ->latest('audit_logs.created_at')->limit(min($request->integer('limit', 50), 100))
            ->get(['audit_logs.*', 'users.name as user_name'])->map(fn (object $row): array => [
                'id' => (string) $row->id, 'action' => $row->action, 'user' => $row->user_name ?? 'System',
                'details' => $row->details, 'time' => $row->created_at, 'status' => $row->status,
            ]);

        return response()->json(['logs' => $logs]);
    }

    public function appReports(): JsonResponse
    {
        $reports = DB::table('app_reports')->leftJoin('users', 'users.id', '=', 'app_reports.submitted_by')
            ->latest('app_reports.created_at')->get(['app_reports.*', 'users.name as submitted_by_name'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id, 'reportId' => 'REP-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                'title' => $row->title, 'severity' => $row->severity, 'status' => $row->status,
                'submittedBy' => $row->submitted_by_name ?? 'Unknown', 'date' => $row->created_at,
            ]);

        return response()->json(['reports' => $reports]);
    }

    public function updateAppReport(Request $request, int $report): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'investigating', 'resolved', 'closed'])]]);
        abort_unless(DB::table('app_reports')->where('id', $report)->update(['status' => $data['status'], 'updated_at' => now()]) > 0, 404, 'Report not found.');
        $this->audit($request, 'App report updated', 'Changed report '.$report.' to '.$data['status'].'.');

        return response()->json(['message' => 'Report updated.']);
    }

    private function audit(Request $request, string $action, string $details): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'details' => $details, 'status' => 'Success', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, bool|int|string> */
    private function settingsRow(): array
    {
        $row = DB::table('super_admin_settings')->where('id', 1)->first();

        return [
            'schoolName' => $row?->school_name ?? 'University Attendance Portal',
            'attendanceRequired' => (int) ($row?->attendance_required ?? 75),
            'proxyDetection' => (bool) ($row?->proxy_detection ?? true),
            'requireAdminMfa' => (bool) ($row?->require_admin_mfa ?? false),
            'systemLockdown' => (bool) ($row?->system_lockdown ?? false),
            'sessionTimeoutMinutes' => (int) ($row?->session_timeout_minutes ?? 30),
            'passwordExpiryDays' => (int) ($row?->password_expiry_days ?? 90),
            'lowAttendanceAlerts' => (bool) ($row?->low_attendance_alerts ?? true),
            'systemHealthReports' => (bool) ($row?->system_health_reports ?? true),
        ];
    }

    private function userRow(User $user): array
    {
        $role = $user->role === 'faculty' ? 'admin' : $user->role;
        $labels = ['student' => 'Student', 'admin' => 'Professor', 'program_head' => 'Program Head', 'super_admin' => 'Super Admin (Developer)'];
        $blocks = $user->assignedSubjects->flatMap(fn ($subject) => $subject->enrollments->pluck('block.name'))->filter()->unique()->values();

        return ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
            'roleLabel' => $labels[$role] ?? ucfirst($role), 'department' => $user->student?->subject ?? 'Information Technology',
            'status' => $user->is_active ? 'Active' : 'Inactive', 'handledBlocks' => $blocks];
    }

    private function validateUser(Request $request, ?int $ignoreId, bool $passwordRequired): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'password' => PasswordPolicy::baseRules($passwordRequired),
            'role' => ['required', Rule::in(['student', 'admin', 'program_head', 'super_admin'])],
            'handledBlocks' => ['nullable', 'array'], 'handledBlocks.*' => ['string', 'max:50'],
        ]);
    }
}
