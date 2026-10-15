<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AttendanceConfirmation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class Esp8266Controller extends Controller
{
    public function enrolledStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
        ]);

        $this->authorizeDevice(
            $request,
            $data['device_id'],
            'students:create'
        );

        $students = DB::table('device_fingerprints')
            ->join('students', 'students.student_number', '=', 'device_fingerprints.student_id')
            ->where('device_fingerprints.device_id', $data['device_id'])
            ->whereNull('students.deleted_at')
            ->orderBy('device_fingerprints.fingerprint_id')
            ->get([
                'device_fingerprints.fingerprint_id as id',
                'device_fingerprints.created_at as timestamp',
                'students.student_number as student_id',
                'students.first_name',
                'students.last_name',
            ]);

        return response()->json([
            'total' => $students->count(),
            'items' => $students,
            'source' => 'backend',
        ]);
    }

    public function registerStudent(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'student_id' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc',
                'max:48',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Str::endsWith($value, ['@phinmaed.com', '@phinmaed.edu.ph'])) {
                        $fail('Use your PHINMAED email address.');
                    }
                },
            ],
            'fingerprint_id' => ['required', 'integer', 'min:1', 'max:1000'],
            'enrolled_at' => ['nullable', 'integer'],
        ]);

        $this->authorizeDevice(
            $request,
            $data['device_id'],
            'students:create'
        );

        return DB::transaction(function () use ($data): JsonResponse {
            $student = DB::table('students')
                ->where('student_number', $data['student_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            $emailIsTaken = DB::table('students')
                ->where('email', $data['email'])
                ->where('student_number', '!=', $data['student_id'])
                ->whereNull('deleted_at')
                ->exists();

            if ($emailIsTaken) {
                throw ValidationException::withMessages([
                    'email' => 'This PHINMAED email is already linked to another student.',
                ]);
            }

            $existingStudent = DB::table('device_fingerprints')
                ->where('student_id', $data['student_id'])
                ->first();

            if ($existingStudent && $existingStudent->device_id !== $data['device_id']) {
                return response()->json([
                    'message' => 'This student is linked to a fingerprint on another device.',
                ], 409);
            }

            $existingSlot = DB::table('device_fingerprints')
                ->where('device_id', $data['device_id'])
                ->where('fingerprint_id', $data['fingerprint_id'])
                ->first();

            $reassignedSlot = $existingStudent === null
                && $existingSlot !== null
                && $existingSlot->student_id !== $data['student_id'];

            if ($reassignedSlot) {
                DB::table('device_fingerprints')
                    ->where('id', $existingSlot->id)
                    ->delete();
                $existingSlot = null;
            }

            if ($student) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'updated_at' => now(),
                    ]);
            } else {
                $studentId = DB::table('students')->insertGetId([
                    'student_number' => $data['student_id'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'subject' => null,
                    'year_level' => 4,
                    'block_id' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => $student ? 'IoT student record updated' : 'IoT student record created',
                'details' => 'Device '.$data['device_id'].' synchronized student '.$data['student_id'].'.',
                'status' => 'Success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($existingStudent) {
                return response()->json([
                    'message' => 'Student details and email updated; the existing fingerprint is unchanged.',
                    'student_id' => $data['student_id'],
                    'email' => $data['email'],
                    'fingerprint_id' => $existingStudent->fingerprint_id,
                    'updated' => true,
                ]);
            }

            if ($existingSlot && $existingSlot->student_id === $data['student_id']) {
                return response()->json([
                    'message' => 'Student details and email updated; the existing fingerprint is unchanged.',
                    'student_id' => $data['student_id'],
                    'email' => $data['email'],
                    'fingerprint_id' => $data['fingerprint_id'],
                    'updated' => true,
                ]);
            }

            DB::table('device_fingerprints')->insert([
                'student_id' => $data['student_id'],
                'device_id' => $data['device_id'],
                'fingerprint_id' => $data['fingerprint_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => 'IoT student registration',
                'details' => 'Device '.$data['device_id'].' registered fingerprint '.$data['fingerprint_id'].' for student '.$data['student_id'].'.',
                'status' => 'Success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => $reassignedSlot
                    ? 'Student and fingerprint saved; stale sensor slot mapping replaced.'
                    : 'Student and fingerprint saved.',
                'student_id' => $data['student_id'],
                'email' => $data['email'],
                'fingerprint_id' => $data['fingerprint_id'],
                'updated' => false,
                'reassigned' => $reassignedSlot,
            ], 201);
        });
    }

    public function recordAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'fingerprint_id' => ['required', 'integer', 'min:1', 'max:1000'],
            'scanned_at' => ['nullable', 'integer'],
        ]);

        $this->authorizeDevice(
            $request,
            $data['device_id'],
            'attendance:create'
        );

        $fingerprint = DB::table('device_fingerprints')
            ->where('device_id', $data['device_id'])
            ->where('fingerprint_id', $data['fingerprint_id'])
            ->first();

        if (! $fingerprint) {
            return response()->json([
                'message' => 'Fingerprint is not linked to a student.',
            ], 404);
        }

        $student = DB::table('students')
            ->where('student_number', $fingerprint->student_id)
            ->whereNull('deleted_at')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'The linked student record is unavailable.',
            ], 404);
        }

        $email = mb_strtolower(trim((string) $student->email));
        if (! Str::endsWith($email, ['@phinmaed.com', '@phinmaed.edu.ph'])) {
            return response()->json([
                'code' => 'phinmaed_email_required',
                'message' => 'A valid PHINMAED email address is required before attendance can be recorded.',
            ], 422);
        }

        $scannedAt = CarbonImmutable::now();
        $subjects = DB::table('enrollments')
            ->join('subjects', 'subjects.id', '=', 'enrollments.subject_id')
            ->leftJoin('class_schedules', function ($join): void {
                $join->on('class_schedules.subject_id', '=', 'subjects.id')
                    ->on('class_schedules.block_id', '=', 'enrollments.block_id');
            })
            ->where('enrollments.student_id', $student->id)
            ->where('enrollments.block_id', $student->block_id)
            ->whereRaw('COALESCE(class_schedules.day, subjects.day) = ?', [$scannedAt->format('l')])
            ->whereRaw('COALESCE(class_schedules.start_time, subjects.start_time) <= ?', [$scannedAt->format('H:i:s')])
            ->whereRaw('COALESCE(class_schedules.end_time, subjects.end_time) > ?', [$scannedAt->format('H:i:s')])
            ->select([
                'subjects.id',
                'subjects.code',
                'subjects.description',
            ])
            ->get();

        if ($subjects->count() !== 1) {
            return response()->json([
                'code' => 'no_subject_for_time',
                'message' => 'No single subject is scheduled for this student at this time.',
            ], 409);
        }

        $subject = $subjects->first();
        $existingAttendance = DB::table('fingerprint_attendance_events')
            ->where('student_id', $fingerprint->student_id)
            ->where('subject_id', $subject->id)
            ->whereDate('scanned_at', $scannedAt->toDateString())
            ->orderBy('scanned_at')
            ->first();

        if ($existingAttendance) {
            return $this->duplicateAttendanceResponse($existingAttendance, $fingerprint->student_id, $subject);
        }

        try {
            $id = DB::table('fingerprint_attendance_events')->insertGetId([
                'student_id' => $fingerprint->student_id,
                'device_id' => $data['device_id'],
                'fingerprint_id' => $data['fingerprint_id'],
                'subject_id' => $subject->id,
                'attendance_date' => $scannedAt->toDateString(),
                'scanned_at' => $scannedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existingAttendance = DB::table('fingerprint_attendance_events')
                ->where('student_id', $fingerprint->student_id)
                ->where('subject_id', $subject->id)
                ->where('attendance_date', $scannedAt->toDateString())
                ->first();
            if (! $existingAttendance) {
                throw $exception;
            }

            return $this->duplicateAttendanceResponse($existingAttendance, $fingerprint->student_id, $subject);
        }

        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'IoT attendance recorded',
            'details' => 'Device '.$data['device_id'].' recorded fingerprint '.$data['fingerprint_id'].' for student '.$fingerprint->student_id.'.',
            'status' => 'Success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $emailSent = false;

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::warning('Attendance confirmation email is not configured for external delivery.', [
                'attendance_id' => $id,
                'student_number' => $student->student_number,
            ]);
        } else {
            try {
                Mail::to($email)->send(new AttendanceConfirmation(
                    studentName: trim($student->first_name.' '.$student->last_name),
                    subjectCode: $subject->code,
                    subjectName: $subject->description,
                    attendedAt: $scannedAt,
                ));
                DB::table('fingerprint_attendance_events')
                    ->where('id', $id)
                    ->update(['email_sent_at' => now(), 'updated_at' => now()]);
                $emailSent = true;
            } catch (TransportExceptionInterface $exception) {
                Log::error('Attendance confirmation email could not be delivered.', [
                    'attendance_id' => $id,
                    'student_number' => $student->student_number,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => $emailSent
                ? 'Attendance recorded and confirmation email sent.'
                : 'Attendance recorded, but the confirmation email could not be delivered.',
            'attendance_id' => $id,
            'student_id' => $fingerprint->student_id,
            'subject' => [
                'code' => $subject->code,
                'name' => $subject->description,
            ],
            'scanned_at' => $scannedAt->toIso8601String(),
            'email_sent' => $emailSent,
            'duplicate' => false,
        ], 201);
    }

    private function duplicateAttendanceResponse(object $attendance, string $studentId, object $subject): JsonResponse
    {
        return response()->json([
            'code' => 'attendance_already_recorded',
            'message' => 'Attendance is already recorded for this class.',
            'attendance_id' => $attendance->id,
            'student_id' => $studentId,
            'subject' => [
                'code' => $subject->code,
                'name' => $subject->description,
            ],
            'scanned_at' => CarbonImmutable::parse($attendance->scanned_at)->toIso8601String(),
            'duplicate' => true,
        ]);
    }

    private function authorizeDevice(
        Request $request,
        string $deviceId,
        string $ability
    ): void {
        $token = $request->user()->currentAccessToken();

        abort_unless(
            $token
            && $token->name === $deviceId
            && $request->user()->tokenCan($ability),
            403,
            'Device token or permission is invalid.'
        );
    }
}
