<?php

namespace App\Http\Controllers\Api;

use App\AttendanceSummary;
use App\ClassScheduleManager;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Security\IdleTimeout;
use App\Security\PasswordPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FrontendController extends Controller
{
    public function sessionSettings(IdleTimeout $idleTimeout): JsonResponse
    {
        return response()->json(['settings' => $idleTimeout->browserSettings()]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $announcementsReadAt = DB::table('portal_notification_reads')
            ->where('user_id', $user->id)
            ->value('announcements_read_at');
        $announcementQuery = DB::table('portal_announcements')
            ->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
            ->join('users', 'users.id', '=', 'portal_announcements.author_id')
            ->where('portal_announcements.published_at', '<=', now())
            ->whereNull('portal_announcements.archived_at')
            ->where(fn (QueryBuilder $query) => $query
                ->whereNull('portal_announcements.expires_at')
                ->orWhere('portal_announcements.expires_at', '>', now()))
            ->when($user->role === 'faculty', fn (QueryBuilder $query) => $query->where(function (QueryBuilder $audience) use ($user): void {
                $audience->whereIn('portal_announcements.audience', ['all', 'faculty'])
                    ->orWhere(fn (QueryBuilder $subject) => $subject
                        ->where('portal_announcements.audience', 'subject')
                        ->where('subjects.faculty_id', $user->id));
            }));
        $unreadCount = (clone $announcementQuery)
            ->when($announcementsReadAt, fn (QueryBuilder $query) => $query->where('portal_announcements.published_at', '>', $announcementsReadAt))
            ->count('portal_announcements.id');
        $announcements = $announcementQuery
            ->select(
                'portal_announcements.id',
                'portal_announcements.title',
                'portal_announcements.body',
                'portal_announcements.published_at',
                'subjects.code as subject_code',
                'users.name as author_name',
            )
            ->orderByDesc('portal_announcements.published_at')
            ->limit(8)
            ->get()
            ->map(fn (object $announcement): array => [
                'id' => 'announcement-'.$announcement->id,
                'label' => $announcement->title.' · '.($announcement->subject_code ?? 'Campus-wide').' · '.$announcement->author_name,
                'count' => 0,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'detail' => ($announcement->subject_code ?? 'Campus-wide').' · '.$announcement->author_name,
                'timestamp' => $announcement->published_at,
            ])
            ->values();

        return response()->json([
            'items' => $announcements,
            'totalCount' => $unreadCount,
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        DB::table('portal_notification_reads')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [
                'announcements_read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function attendanceReports(Request $request, AttendanceSummary $attendance): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'block' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['present', 'absent', 'late'])],
        ]);
        $fromDate = CarbonImmutable::parse($data['from'] ?? $data['date'] ?? 'today');
        $toDate = CarbonImmutable::parse($data['to'] ?? $data['date'] ?? 'today');
        if ($fromDate->startOfDay()->diffInDays($toDate->startOfDay()) > 6) {
            throw ValidationException::withMessages([
                'to' => ['Attendance reports can cover no more than one week.'],
            ]);
        }
        $from = $fromDate->startOfDay();
        $to = $toDate->endOfDay();
        $blocks = $attendance->enrollments($request->user())
            ->get()
            ->pluck('block')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
        $block = $blocks->firstWhere('name', $data['block'] ?? null);
        $scheduledWeekdays = $attendance->subjects($request->user())
            ->whereHas('enrollments', fn ($query) => $query
                ->when($block, fn ($enrollments) => $enrollments->where('block_id', $block->id)))
            ->pluck('day')
            ->unique();
        $scheduledDates = collect(CarbonPeriod::create($from->startOfDay(), $to->startOfDay()))
            ->filter(fn ($date): bool => $scheduledWeekdays->contains($date->format('l')))
            ->map(fn ($date): string => $date->toDateString())
            ->values();

        $records = $attendance->records($request->user(), [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'block_id' => $block?->id,
            'status' => $data['status'] ?? null,
        ])->reject(fn (array $record): bool => $record['status'] === 'pending')
            ->map(function (array $record): array {
                $enrollment = $record['enrollment'];
                $student = $enrollment->student;
                $block = $enrollment->block;

                return [
                    'id' => $enrollment->id.'-'.$record['date'],
                    'date' => $record['date'],
                    'class' => 'Year '.$student->year_level.' / '.$block->name,
                    'status' => ucfirst($record['status']),
                    'student' => [
                        'id' => (string) $student->id,
                        'name' => $student->full_name,
                        'idNumber' => $student->student_number,
                        'email' => $student->email,
                        'block' => $block->name,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'reports' => $records,
            'blocks' => $blocks->pluck('name'),
            'scheduledDates' => $scheduledDates,
        ]);
    }

    public function adminOverview(Request $request, AttendanceSummary $attendance): JsonResponse
    {
        if ($request->user()->role === 'super_admin') {
            $auditLogs = DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->latest('audit_logs.created_at')
                ->limit(8)
                ->get(['audit_logs.id', 'audit_logs.action', 'audit_logs.details', 'audit_logs.status', 'audit_logs.created_at', 'users.name as user_name'])
                ->map(fn (object $log): array => [
                    'id' => (string) $log->id,
                    'action' => $log->action,
                    'details' => $log->details,
                    'status' => $log->status,
                    'user' => $log->user_name ?? 'IoT Device',
                    'time' => $log->created_at,
                ]);

            return response()->json([
                'stats' => [
                    'totalStudents' => Student::query()->where('status', 'active')->count(),
                    'presentToday' => 0,
                    'absentToday' => 0,
                    'lateToday' => 0,
                ],
                'systemStats' => [
                    'totalUsers' => DB::table('users')->count(),
                    'systemLoad' => 0,
                    'reportsFiled' => DB::table('app_reports')->count(),
                    'activeAlerts' => DB::table('app_reports')->whereIn('status', ['open', 'investigating'])->count(),
                    'totalUsersTrend' => 'Live',
                    'systemLoadTrend' => 'Normal',
                    'reportsFiledTrend' => 'Live',
                    'activeAlertsTrend' => 'Live',
                ],
                'attendanceTrendsWeekday' => collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])->map(fn (string $day): array => [
                    'name' => $day, 'present' => 0, 'absent' => 0, 'late' => 0,
                ]),
                'pieData' => [],
                'alerts' => [],
                'auditLogs' => $auditLogs,
            ]);
        }

        $today = CarbonImmutable::today();
        $records = $attendance->records($request->user(), [
            'from' => $today->startOfWeek()->toDateString(),
            'to' => $today->endOfWeek()->toDateString(),
        ]);
        $todayRecords = $records->where('date', $today->toDateString());
        $counts = $attendance->counts($todayRecords);
        $handledStudentCount = $attendance->enrollments($request->user())
            ->whereHas('student', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('status', 'active'))
            ->distinct()
            ->count('student_id');
        $weekdayTrends = collect(CarbonPeriod::create(
            $today->startOfWeek(),
            $today->endOfWeek()
        ))->map(function ($day) use ($records, $attendance): array {
            $dayCounts = $attendance->counts($records->where('date', $day->toDateString()));

            return [
                'name' => $day->format('D'),
                'present' => $dayCounts['present'],
                'absent' => $dayCounts['absent'],
                'late' => $dayCounts['late'],
            ];
        })->values();

        return response()->json([
            'stats' => [
                'totalStudents' => $handledStudentCount,
                'presentToday' => $counts['present'],
                'absentToday' => $counts['absent'],
                'lateToday' => $counts['late'],
            ],
            'attendanceTrendsWeekday' => $weekdayTrends,
            'pieData' => [
                ['name' => 'Present', 'value' => $counts['present'], 'color' => '#10b981'],
                ['name' => 'Late', 'value' => $counts['late'], 'color' => '#f59e0b'],
                ['name' => 'Absent', 'value' => $counts['absent'], 'color' => '#ef4444'],
            ],
            'alerts' => [],
        ]);
    }

    public function facultySchedule(Request $request, ClassScheduleManager $schedules): JsonResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $classes = $user->assignedSubjects()
            ->with(['enrollments.block', 'classSchedules'])
            ->orderBy('start_time')
            ->get()
            ->flatMap(function ($subject) use ($schedules, $today, $user): array {
                return $subject->enrollments
                    ->whereNotNull('block_id')
                    ->groupBy('block_id')
                    ->map(function ($enrollments) use ($schedules, $subject, $today, $user): ?array {
                        $block = $enrollments->first()->block;
                        $classSchedule = $schedules->effective($subject, $block->id);
                        if ($classSchedule['day'] !== $today->format('l')) {
                            return null;
                        }
                        $graceMinutes = DB::table('class_grace_periods')
                            ->where('user_id', $user->id)
                            ->where('subject_id', $subject->id)
                            ->where('block_id', $block->id)
                            ->value('late_minutes');

                        return [
                            'id' => $subject->id.'-'.$block->id,
                            'subjectId' => $subject->id,
                            'blockId' => $block->id,
                            'subjectCode' => $subject->code,
                            'subjectName' => $subject->description,
                            'block' => $block->name,
                            'studentCount' => $enrollments->pluck('student_id')->unique()->count(),
                            'schedule' => CarbonImmutable::createFromFormat('H:i', $classSchedule['start_time'])->format('g:i A').' - '.CarbonImmutable::createFromFormat('H:i', $classSchedule['end_time'])->format('g:i A'),
                            'room' => $classSchedule['room'],
                            'graceMinutes' => (int) ($graceMinutes ?? 10),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values();

        return response()->json([
            'teacherName' => $user->name,
            'date' => $today->toDateString(),
            'day' => $today->format('l'),
            'classes' => $classes,
        ]);
    }

    public function updateClassGracePeriod(Request $request, int $subject, int $block): JsonResponse
    {
        $data = $request->validate([
            'graceMinutes' => ['required', 'integer', 'between:0,60'],
        ]);
        $user = $request->user();
        $ownsClass = $user->assignedSubjects()
            ->whereKey($subject)
            ->whereHas('enrollments', fn ($query) => $query->where('block_id', $block))
            ->exists();

        abort_unless($ownsClass, 404, 'This class is not assigned to you.');

        DB::table('class_grace_periods')->updateOrInsert(
            ['user_id' => $user->id, 'subject_id' => $subject, 'block_id' => $block],
            [
                'late_minutes' => $data['graceMinutes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action' => 'Class grace period updated',
            'details' => 'Changed subject '.$subject.', block '.$block.' grace period to '.$data['graceMinutes'].' minutes.',
            'status' => 'Success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Class grace period updated.',
            'graceMinutes' => $data['graceMinutes'],
        ]);
    }

    public function archiveRequests(Request $request): JsonResponse
    {
        $requests = DB::table('archive_requests')
            ->join('users', 'users.id', '=', 'archive_requests.requested_by')
            ->when($request->user()->role !== 'super_admin', fn ($query) => $query->where('archive_requests.requested_by', $request->user()->id))
            ->latest('archive_requests.created_at')
            ->select(['archive_requests.*', 'users.name as requested_by_name'])
            ->get()
            ->map(fn (object $archiveRequest): array => [
                'id' => (string) $archiveRequest->id,
                'requestId' => 'AR-'.str_pad((string) $archiveRequest->id, 5, '0', STR_PAD_LEFT),
                'student' => $archiveRequest->student_name,
                'block' => $archiveRequest->student_block,
                'reason' => $archiveRequest->reason,
                'status' => $archiveRequest->status,
                'requestedBy' => $archiveRequest->requested_by_name,
            ]);

        return response()->json(['requests' => $requests]);
    }

    public function storeArchiveRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'studentName' => ['required', 'string', 'max:255'],
            'studentBlock' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $id = DB::table('archive_requests')->insertGetId([
            'requested_by' => $request->user()->id,
            'student_name' => $data['studentName'],
            'student_block' => $data['studentBlock'],
            'reason' => $data['reason'],
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'Archive request submitted',
            'details' => 'Created archive request '.$id.' for '.$data['studentName'].'.',
            'status' => 'Success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Archive request submitted.', 'id' => $id], 201);
    }

    public function updateArchiveRequest(Request $request, int $archiveRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Reviewed', 'Approved', 'Rejected'])],
        ]);
        $updated = DB::table('archive_requests')->where('id', $archiveRequest)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        abort_unless($updated > 0, 404, 'Archive request not found.');
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'Archive request updated',
            'details' => 'Changed archive request '.$archiveRequest.' to '.$data['status'].'.',
            'status' => 'Success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Archive request updated.']);
    }

    public function studentProfile(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);

        return response()->json([
            'profile' => [
                'name' => $request->user()->name,
                'block' => $student->block?->name ?? '',
                'email' => $student->email,
            ],
        ]);
    }

    public function updateStudentProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'block' => ['required', 'string', 'max:50'],
        ]);
        $student = $this->studentFor($request);
        $currentBlock = $student->block?->name ?? '';

        if ($data['block'] !== $currentBlock) {
            throw ValidationException::withMessages([
                'block' => 'Contact your administrator to change your block.',
            ]);
        }

        $request->user()->update(['name' => $data['name']]);

        return $this->studentProfile($request);
    }

    public function updateStudentPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => PasswordPolicy::rules($request->user()),
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        if (! Hash::check($data['currentPassword'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'The current password is incorrect.',
            ]);
        }

        PasswordPolicy::change($request->user(), $data['newPassword']);

        return response()->json(['message' => 'Password updated.']);
    }

    public function studentCourses(Request $request, ClassScheduleManager $schedules): JsonResponse
    {
        $student = $this->studentFor($request);

        return response()->json([
            'courses' => $student->subjects()
                ->with('classSchedules')
                ->orderBy('day')
                ->orderBy('start_time')
                ->get()
                ->map(function ($subject) use ($schedules): array {
                    $schedule = $schedules->effective($subject, $subject->pivot->block_id);

                    return [
                        'courseName' => $subject->code.' - '.$subject->description,
                        'schedule' => $schedule['day'].' '.CarbonImmutable::createFromFormat('H:i', $schedule['start_time'])->format('g:i A').' - '.CarbonImmutable::createFromFormat('H:i', $schedule['end_time'])->format('g:i A'),
                        'room' => $schedule['room'],
                        'hasPendingChange' => false,
                        'requestedSchedule' => '',
                        'requestedRoom' => '',
                        'status' => null,
                    ];
                }),
        ]);
    }

    public function studentAttendance(Request $request, ClassScheduleManager $schedules): JsonResponse
    {
        $student = $this->studentFor($request);
        $now = CarbonImmutable::now();
        $from = $now->startOfDay()->subDays(29);
        $subjects = $student->subjects()->with('classSchedules')->get();
        $classGracePeriods = DB::table('class_grace_periods')
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->get()
            ->keyBy(fn (object $period): string => $period->user_id.'|'.$period->subject_id.'|'.$period->block_id);
        $events = DB::table('fingerprint_attendance_events')
            ->where('student_id', $student->student_number)
            ->whereBetween('scanned_at', [$from, $now])
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->orderBy('scanned_at')
            ->get()
            ->groupBy(fn (object $event): string => $event->subject_id.'|'.CarbonImmutable::parse($event->scanned_at)->toDateString());
        $records = collect();
        $counts = ['presentDays' => 0, 'lateDays' => 0, 'absentDays' => 0];

        foreach (CarbonPeriod::create($from->startOfDay(), $now->startOfDay()) as $day) {
            foreach ($subjects as $subject) {
                $schedule = $schedules->effective($subject, $subject->pivot->block_id);
                if ($schedule['day'] !== $day->format('l') || $subject->pivot->created_at->isAfter($day->toDateString().' '.$schedule['start_time'])) {
                    continue;
                }

                $start = CarbonImmutable::parse($day->toDateString().' '.$schedule['start_time']);
                $end = CarbonImmutable::parse($day->toDateString().' '.$schedule['end_time']);
                if ($end->lessThanOrEqualTo($start) || $end->isAfter($now)) {
                    continue;
                }

                $event = $events->get($subject->id.'|'.$day->toDateString())?->first(
                    fn (object $candidate): bool => CarbonImmutable::parse($candidate->scanned_at)->greaterThanOrEqualTo($start)
                        && CarbonImmutable::parse($candidate->scanned_at)->lessThan($end)
                );
                $scannedAt = $event ? CarbonImmutable::parse($event->scanned_at) : null;
                $lateMinutes = (int) ($classGracePeriods->get(
                    $subject->faculty_id.'|'.$subject->id.'|'.$subject->pivot->block_id
                )?->late_minutes ?? 10);
                $status = $scannedAt
                    ? ($scannedAt->greaterThan($start->addMinutes($lateMinutes)) ? 'Late' : 'Present')
                    : 'Absent';
                $countKey = match ($status) {
                    'Present' => 'presentDays',
                    'Late' => 'lateDays',
                    default => 'absentDays',
                };
                $counts[$countKey]++;
                $records->push([
                    'id' => $event->id ?? $subject->id.'-'.$day->toDateString(),
                    'date' => $day->toDateString(),
                    'status' => $status,
                    'time' => $scannedAt?->format('g:i A') ?? '',
                    'note' => $subject->code.' - '.$subject->description,
                ]);
            }
        }

        return response()->json([
            'records' => $records->sortByDesc(fn (array $record): string => $record['date'].' '.$record['time'])->take(100)->values(),
            'stats' => $counts,
        ]);
    }

    private function studentFor(Request $request): Student
    {
        $student = $request->user()->student()->with(['block', 'subjects'])->first();

        abort_unless($student, 404, 'This account is not linked to a student record.');

        return $student;
    }
}
