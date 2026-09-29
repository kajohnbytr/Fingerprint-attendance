<?php

namespace App\Http\Controllers\Api;

use App\AttendanceSummary;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FrontendController extends Controller
{
    public function attendanceReports(Request $request, AttendanceSummary $attendance): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'block' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['present', 'absent', 'late'])],
        ]);
        $date = CarbonImmutable::parse($data['date'] ?? 'today')->toDateString();
        $blocks = $attendance->enrollments($request->user())
            ->get()
            ->pluck('block')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
        $block = $blocks->firstWhere('name', $data['block'] ?? null);

        $records = $attendance->records($request->user(), [
            'from' => $date,
            'to' => $date,
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
            });

        return response()->json([
            'reports' => $records,
            'blocks' => $blocks->pluck('name'),
        ]);
    }

    public function adminOverview(Request $request, AttendanceSummary $attendance): JsonResponse
    {
        $today = CarbonImmutable::today();
        $records = $attendance->records($request->user(), [
            'from' => $today->startOfWeek()->toDateString(),
            'to' => $today->endOfWeek()->toDateString(),
        ]);
        $todayRecords = $records->where('date', $today->toDateString());
        $counts = $attendance->counts($todayRecords);
        $weekdayTrends = collect(CarbonPeriod::create(
            $today->startOfWeek(),
            $today->startOfWeek()->addDays(4)
        ))->map(function (CarbonImmutable $day) use ($records, $attendance): array {
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
                'totalStudents' => Student::query()->where('status', 'active')->count(),
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

    public function facultySchedule(Request $request, AttendanceSummary $attendance): JsonResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $classes = $user->assignedSubjects()
            ->where('day', $today->format('l'))
            ->with(['enrollments.block'])
            ->orderBy('start_time')
            ->get()
            ->flatMap(function ($subject): array {
                return $subject->enrollments
                    ->whereNotNull('block_id')
                    ->groupBy('block_id')
                    ->map(function ($enrollments) use ($subject): array {
                        $block = $enrollments->first()->block;

                        return [
                            'id' => $subject->id.'-'.$block->id,
                            'subjectCode' => $subject->code,
                            'subjectName' => $subject->description,
                            'block' => $block->name,
                            'studentCount' => $enrollments->pluck('student_id')->unique()->count(),
                            'schedule' => $subject->start_time->format('g:i A').' - '.$subject->end_time->format('g:i A'),
                            'room' => $subject->room,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values();

        return response()->json([
            'teacherName' => $user->name,
            'date' => $today->toDateString(),
            'day' => $today->format('l'),
            'classes' => $classes,
            'graceMinutes' => $attendance->preferences($user)['late_minutes'],
        ]);
    }

    public function updateFacultyGracePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'graceMinutes' => ['required', 'integer', 'between:0,60'],
        ]);
        $user = $request->user();
        $existing = DB::table('faculty_preferences')->where('user_id', $user->id)->first();

        DB::table('faculty_preferences')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'late_minutes' => $data['graceMinutes'],
                'report_days' => $existing->report_days ?? 30,
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Attendance grace period updated.',
            'graceMinutes' => $data['graceMinutes'],
        ]);
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
            'newPassword' => ['required', 'string', 'min:8'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        if (! Hash::check($data['currentPassword'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'The current password is incorrect.',
            ]);
        }

        $request->user()->update(['password' => $data['newPassword']]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function studentCourses(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);

        return response()->json([
            'courses' => $student->subjects()
                ->orderBy('day')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($subject): array => [
                    'courseName' => $subject->code.' - '.$subject->description,
                    'schedule' => $subject->day.' '.$subject->start_time->format('g:i A').' - '.$subject->end_time->format('g:i A'),
                    'room' => $subject->room,
                    'hasPendingChange' => false,
                    'requestedSchedule' => '',
                    'requestedRoom' => '',
                    'status' => null,
                ]),
        ]);
    }

    public function studentAttendance(Request $request): JsonResponse
    {
        $student = $this->studentFor($request);
        $now = CarbonImmutable::now();
        $from = $now->startOfDay()->subDays(29);
        $subjects = $student->subjects()->get();
        $lateMinutes = (int) (DB::table('system_settings')->where('id', 1)->value('late_minutes') ?? 15);
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
                if ($subject->day !== $day->format('l') || $subject->pivot->created_at->isAfter($day->toDateString().' '.$subject->start_time->format('H:i:s'))) {
                    continue;
                }

                $start = CarbonImmutable::parse($day->toDateString().' '.$subject->start_time->format('H:i:s'));
                $end = CarbonImmutable::parse($day->toDateString().' '.$subject->end_time->format('H:i:s'));
                if ($end->lessThanOrEqualTo($start) || $end->isAfter($now)) {
                    continue;
                }

                $event = $events->get($subject->id.'|'.$day->toDateString())?->first(
                    fn (object $candidate): bool => CarbonImmutable::parse($candidate->scanned_at)->greaterThanOrEqualTo($start)
                        && CarbonImmutable::parse($candidate->scanned_at)->lessThan($end)
                );
                $scannedAt = $event ? CarbonImmutable::parse($event->scanned_at) : null;
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
