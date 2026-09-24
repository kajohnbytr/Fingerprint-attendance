<?php

namespace App\Http\Controllers;

use App\Models\Fingerprint;
use App\Models\FingerprintEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FingerprintController extends Controller
{
    private const DEVICE_ID = 'esp32-01';

    private const MAX_SLOT = 127;

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollment = DB::transaction(function () use ($user) {
            // Lock the user row so two clicks cannot start two enrollments.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            if (Fingerprint::where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'fingerprint' => 'This account already has a fingerprint.',
                ]);
            }

            // This example supports one ESP32 enrolling one person at a time.
            $pending = FingerprintEnrollment::where('device_id', self::DEVICE_ID)
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                throw ValidationException::withMessages([
                    'device' => 'A fingerprint enrollment is already in progress.',
                ]);
            }

            $highestFingerprint = Fingerprint::where('device_id', self::DEVICE_ID)
                ->max('slot') ?? 0;

            $highestAttempt = FingerprintEnrollment::where('device_id', self::DEVICE_ID)
                ->max('slot') ?? 0;

            $slot = max($highestFingerprint, $highestAttempt) + 1;

            if ($slot > self::MAX_SLOT) {
                throw ValidationException::withMessages([
                    'device' => 'The configured fingerprint slots are full.',
                ]);
            }

            return FingerprintEnrollment::create([
                'user_id' => $user->id,
                'device_id' => self::DEVICE_ID,
                'slot' => $slot,
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'id' => $enrollment->id,
            'status' => $enrollment->status,
        ], 201);
    }

    public function status(Request $request, FingerprintEnrollment $enrollment): JsonResponse
    {
        abort_unless($enrollment->user_id === $request->user()->id, 403);

        return response()->json([
            'status' => $enrollment->status,
            'error' => $enrollment->error,
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorizeDevice($request);

        $enrollment = FingerprintEnrollment::where('device_id', self::DEVICE_ID)
            ->where('status', 'pending')
            ->oldest()
            ->first();

        return response()->json([
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'slot' => $enrollment->slot,
            ] : null,
        ]);
    }

    public function complete(Request $request, FingerprintEnrollment $enrollment): JsonResponse
    {
        $this->authorizeDevice($request);

        $data = $request->validate([
            'slot' => ['required', 'integer'],
            'success' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($enrollment, $data) {
            $locked = FingerprintEnrollment::whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $locked->device_id === self::DEVICE_ID
                && $locked->slot === (int) $data['slot'],
                422,
                'Enrollment and device slot do not match.'
            );

            if ($locked->status !== 'pending') {
                return response()->json(['status' => $locked->status]);
            }

            if (! $data['success']) {
                $locked->update([
                    'status' => 'failed',
                    'error' => $data['error'] ?? 'Sensor enrollment failed.',
                ]);

                return response()->json(['status' => 'failed']);
            }

            Fingerprint::create([
                'user_id' => $locked->user_id,
                'device_id' => $locked->device_id,
                'slot' => $locked->slot,
            ]);

            $locked->update(['status' => 'completed']);

            return response()->json(['status' => 'completed']);
        });
    }

    private function authorizeDevice(Request $request): void
    {
        $token = $request->user()->currentAccessToken();
        abort_unless(
            $token && $token->name === self::DEVICE_ID
                && $request->user()->tokenCan('fingerprint:enroll'),
            403,
            'Token cannot enroll fingerprints.'
        );
    }
}
