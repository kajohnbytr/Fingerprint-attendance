<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Esp8266Controller extends Controller
{
    public function registerStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'student_id' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
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

            if (! $student) {
                $studentId = DB::table('students')->insertGetId([
                    'student_number' => $data['student_id'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => null,
                    'subject' => null,
                    'year_level' => 4,
                    'block_id' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $student = DB::table('students')
                    ->where('id', $studentId)
                    ->first();
            } else {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'updated_at' => now(),
                    ]);
            }

            $existingSlot = DB::table('device_fingerprints')
                ->where('device_id', $data['device_id'])
                ->where('fingerprint_id', $data['fingerprint_id'])
                ->first();

            if ($existingSlot) {
                if ($existingSlot->student_id === $data['student_id']) {
                    return response()->json([
                        'message' => 'Fingerprint was already linked.',
                        'student_id' => $data['student_id'],
                        'fingerprint_id' => $data['fingerprint_id'],
                    ]);
                }

                return response()->json([
                    'message' => 'This sensor slot belongs to another student.',
                ], 409);
            }

            $existingStudent = DB::table('device_fingerprints')
                ->where('student_id', $data['student_id'])
                ->first();

            if ($existingStudent) {
                return response()->json([
                    'message' => 'This student already has a linked fingerprint.',
                ], 409);
            }

            DB::table('device_fingerprints')->insert([
                'student_id' => $data['student_id'],
                'device_id' => $data['device_id'],
                'fingerprint_id' => $data['fingerprint_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Student and fingerprint saved.',
                'student_id' => $data['student_id'],
                'fingerprint_id' => $data['fingerprint_id'],
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

        $id = DB::table('fingerprint_attendance_events')->insertGetId([
            'student_id' => $fingerprint->student_id,
            'device_id' => $data['device_id'],
            'fingerprint_id' => $data['fingerprint_id'],
            'scanned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance recorded.',
            'attendance_id' => $id,
            'student_id' => $fingerprint->student_id,
        ], 201);
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
