<?php

namespace Tests\Feature;

use App\Mail\AttendanceConfirmation;
use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeviceWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_telemetry_saves_measurements_and_requires_the_correct_device_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('sensor-01', ['readings:create'])->plainTextToken;
        $payload = ['device_id' => 'sensor-01', 'temperature' => 28.25, 'humidity' => 65.5];
        $this->withToken($token)->postJson('/api/telemetry', $payload)->assertCreated();
        $this->assertDatabaseHas('readings', $payload);
        $this->postJson('/api/telemetry', [...$payload, 'device_id' => 'other-sensor'])->assertForbidden();
        $this->postJson('/api/telemetry', [...$payload, 'humidity' => 101])->assertUnprocessable();
        $this->assertDatabaseCount('readings', 1);
    }

    public function test_device_token_remains_valid_after_extended_inactivity(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('sensor-01', ['attendance:create']);
        $newToken->accessToken->forceFill([
            'last_used_at' => now()->subYear(),
        ])->save();

        $this->withToken($newToken->plainTextToken)
            ->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('database', true);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $newToken->accessToken->getKey(),
        ]);
    }

    public function test_attendance_keeps_the_existing_readings_url_and_supports_its_own_endpoint(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $block = Block::factory()->create();
        $student = Student::factory()->create([
            'email' => 'student@phinmaed.com',
            'block_id' => $block->id,
        ]);
        $user = User::factory()->create();
        $now = now();
        $subject = Subject::factory()->create([
            'day' => $now->format('l'),
            'start_time' => $now->copy()->subHour()->format('H:i:s'),
            'end_time' => $now->copy()->addHour()->format('H:i:s'),
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
            'enrolled_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
        ]);
        $this->assertSame($now->format('l'), $subject->day);
        $this->assertTrue($subject->start_time->format('H:i:s') <= $now->format('H:i:s'));
        $this->assertTrue($subject->end_time->format('H:i:s') > $now->format('H:i:s'));
        $token = $user->createToken('sensor-01', ['students:create', 'attendance:create'])->plainTextToken;
        $this->withToken($token)->postJson('/api/students', [
            'device_id' => 'sensor-01', 'student_id' => $student->student_number,
            'first_name' => $student->first_name, 'last_name' => $student->last_name,
            'email' => 'student@phinmaed.com', 'fingerprint_id' => 1,
        ])->assertCreated();
        $this->assertDatabaseHas('students', [
            'student_number' => $student->student_number,
            'email' => 'student@phinmaed.com',
        ]);
        $firstResponse = $this->postJson('/api/readings', ['device_id' => 'sensor-01', 'fingerprint_id' => 1]);
        $firstResponse->assertCreated()
            ->assertJsonPath('student_id', $student->student_number)
            ->assertJsonPath('subject.code', $subject->code)
            ->assertJsonPath('subject.name', $subject->description)
            ->assertJsonPath('email_sent', true)
            ->assertJsonPath('duplicate', false);

        $this->postJson('/api/attendance', ['device_id' => 'sensor-01', 'fingerprint_id' => 1])
            ->assertOk()
            ->assertJsonPath('code', 'attendance_already_recorded')
            ->assertJsonPath('attendance_id', $firstResponse->json('attendance_id'))
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('fingerprint_attendance_events', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'IoT student registration']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'IoT attendance recorded']);
        $this->assertDatabaseHas('fingerprint_attendance_events', [
            'student_id' => $student->student_number,
            'subject_id' => $subject->id,
        ]);
        $this->assertDatabaseCount('readings', 0);
        $this->assertDatabaseCount('students', 1);
        Mail::assertSent(AttendanceConfirmation::class, function (AttendanceConfirmation $mail) use ($student, $subject): bool {
            $body = $mail->render();

            return $mail->hasTo($student->email)
                && str_contains($body, $subject->description)
                && str_contains($body, $subject->code);
        });
    }

    public function test_device_student_registration_requires_and_saves_a_phinmaed_email(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('sensor-01', ['students:create'])->plainTextToken;
        $payload = [
            'device_id' => 'sensor-01',
            'student_id' => '2026-123456',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'fingerprint_id' => 1,
        ];

        $this->withToken($token)->postJson('/api/students', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withToken($token)->postJson('/api/students', [
            ...$payload,
            'email' => 'student@example.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withToken($token)->postJson('/api/students', [
            ...$payload,
            'email' => 'Student@PHINMAED.EDU.PH',
        ])->assertCreated()
            ->assertJsonPath('email', 'student@phinmaed.edu.ph');

        $this->assertDatabaseHas('students', [
            'student_number' => '2026-123456',
            'email' => 'student@phinmaed.edu.ph',
        ]);

        $this->withToken($token)->postJson('/api/students', [
            ...$payload,
            'first_name' => 'Updated',
            'email' => 'newstudent@phinmaed.edu.ph',
            'fingerprint_id' => 2,
        ])->assertOk()
            ->assertJsonPath('email', 'newstudent@phinmaed.edu.ph')
            ->assertJsonPath('fingerprint_id', 1)
            ->assertJsonPath('updated', true);
        $this->assertDatabaseHas('students', [
            'student_number' => '2026-123456',
            'email' => 'newstudent@phinmaed.edu.ph',
            'first_name' => 'Updated',
        ]);
        $this->assertDatabaseCount('device_fingerprints', 1);
    }

    public function test_device_can_list_only_its_enrolled_fingerprint_ids(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('sensor-01', ['students:create'])->plainTextToken;
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();

        DB::table('device_fingerprints')->insert([
            [
                'student_id' => $student->student_number,
                'device_id' => 'sensor-01',
                'fingerprint_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'student_id' => $otherStudent->student_number,
                'device_id' => 'sensor-02',
                'fingerprint_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withToken($token)
            ->getJson('/api/students?device_id=sensor-01')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('source', 'backend')
            ->assertJsonPath('items.0.id', 3)
            ->assertJsonPath('items.0.student_id', $student->student_number)
            ->assertJsonMissing(['id' => 7]);

        $this->withToken($token)
            ->getJson('/api/students?device_id=sensor-02')
            ->assertForbidden();
    }

    public function test_device_registration_replaces_a_stale_mapping_for_a_reused_sensor_slot(): void
    {
        $previousStudent = Student::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('sensor-01', ['students:create'])->plainTextToken;

        DB::table('device_fingerprints')->insert([
            'student_id' => $previousStudent->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)->postJson('/api/students', [
            'device_id' => 'sensor-01',
            'student_id' => '03-2526-038387',
            'first_name' => 'New',
            'last_name' => 'Student',
            'email' => 'new.student@phinmaed.com',
            'fingerprint_id' => 1,
        ])->assertCreated()
            ->assertJsonPath('reassigned', true)
            ->assertJsonPath('fingerprint_id', 1);

        $this->assertDatabaseMissing('device_fingerprints', [
            'student_id' => $previousStudent->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
        ]);
        $this->assertDatabaseHas('device_fingerprints', [
            'student_id' => '03-2526-038387',
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
        ]);
        $this->assertDatabaseHas('students', [
            'student_number' => '03-2526-038387',
            'email' => 'new.student@phinmaed.com',
        ]);
        $this->assertDatabaseCount('device_fingerprints', 1);
    }

    public function test_attendance_is_not_saved_when_no_subject_is_active_for_the_student(): void
    {
        Mail::fake();
        $student = Student::factory()->create([
            'email' => 'student@phinmaed.edu.ph',
        ]);
        $user = User::factory()->create();
        DB::table('device_fingerprints')->insert([
            'student_id' => $student->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $user->createToken('sensor-01', ['attendance:create'])->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance', [
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
        ])->assertConflict()
            ->assertJsonPath('code', 'no_subject_for_time');

        $this->assertDatabaseCount('fingerprint_attendance_events', 0);
        Mail::assertNothingSent();
    }

    public function test_attendance_is_not_saved_when_the_scan_matches_multiple_active_subjects(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $now = now();
        $block = Block::factory()->create();
        $student = Student::factory()->create([
            'email' => 'student@phinmaed.com',
            'block_id' => $block->id,
        ]);
        $user = User::factory()->create();
        foreach (['IT101', 'IT102'] as $code) {
            $subject = Subject::factory()->create([
                'code' => $code,
                'day' => $now->format('l'),
                'start_time' => $now->copy()->subHour()->format('H:i:s'),
                'end_time' => $now->copy()->addHour()->format('H:i:s'),
            ]);
            DB::table('enrollments')->insert([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('device_fingerprints')->insert([
            'student_id' => $student->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $token = $user->createToken('sensor-01', ['attendance:create'])->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance', [
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
        ])->assertConflict()
            ->assertJsonPath('code', 'no_subject_for_time');

        $this->assertDatabaseCount('fingerprint_attendance_events', 0);
        Mail::assertNothingSent();
    }

    public function test_attendance_is_not_saved_without_a_phinmaed_email(): void
    {
        Mail::fake();
        $student = Student::factory()->create([
            'email' => 'student@example.test',
        ]);
        $user = User::factory()->create();
        DB::table('device_fingerprints')->insert([
            'student_id' => $student->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $user->createToken('sensor-01', ['attendance:create'])->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance', [
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'phinmaed_email_required');

        $this->assertDatabaseCount('fingerprint_attendance_events', 0);
        Mail::assertNothingSent();
    }

    public function test_fingerprint_registration_uses_the_existing_table_and_rejects_duplicate_pending_requests(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/fingerprint/enroll')->assertCreated();
        $id = $response->json('id');
        $this->assertDatabaseHas('fingerprint_enrollment', ['id' => $id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->getJson('/fingerprint/enroll/'.$id.'/status')->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson('/fingerprint/enroll')->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson('/fingerprint/enroll/'.$id.'/status')->assertForbidden();
    }

    public function test_device_can_poll_and_complete_enrollment_idempotently(): void
    {
        $user = User::factory()->create();
        $id = DB::table('fingerprint_enrollment')->insertGetId([
            'user_id' => $user->id, 'device_id' => 'esp32-01', 'slot' => 1,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = $user->createToken('esp32-01', ['fingerprint:enroll'])->plainTextToken;
        $this->withToken($token)->getJson('/api/fingerprint/enroll/pending')
            ->assertOk()->assertJsonPath('enrollment.id', $id);
        $this->postJson('/api/fingerprint/enroll/'.$id.'/complete', ['slot' => 2, 'success' => true])->assertUnprocessable();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/api/fingerprint/enroll/'.$id.'/complete', ['slot' => 1, 'success' => true])
                ->assertOk()->assertJsonPath('status', 'completed');
        }
        $this->assertDatabaseCount('fingerprints', 1);
        $this->assertDatabaseHas('fingerprints', ['user_id' => $user->id, 'device_id' => 'esp32-01', 'slot' => 1]);
        $this->getJson('/api/fingerprint/enroll/pending')->assertJsonPath('enrollment', null);
    }

    public function test_device_reports_sensor_failure_without_creating_a_fingerprint(): void
    {
        $user = User::factory()->create();
        $id = DB::table('fingerprint_enrollment')->insertGetId([
            'user_id' => $user->id, 'device_id' => 'esp32-01', 'slot' => 1,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = $user->createToken('esp32-01', ['fingerprint:enroll'])->plainTextToken;
        $this->withToken($token)->postJson('/api/fingerprint/enroll/'.$id.'/complete', [
            'slot' => 1, 'success' => false, 'error' => 'Sensor unavailable',
        ])->assertOk()->assertJsonPath('status', 'failed');
        $this->assertDatabaseHas('fingerprint_enrollment', ['id' => $id, 'error' => 'Sensor unavailable']);
        $this->assertDatabaseCount('fingerprints', 0);
    }

    public function test_device_routes_reject_missing_tokens_wrong_devices_and_missing_abilities(): void
    {
        $this->getJson('/api/fingerprint/enroll/pending')->assertUnauthorized();
        $this->postJson('/api/telemetry', [])->assertUnauthorized();
        $user = User::factory()->create();
        $wrongDevice = $user->createToken('other-device', ['fingerprint:enroll'])->plainTextToken;
        $this->withToken($wrongDevice)->getJson('/api/fingerprint/enroll/pending')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $wrongAbility = $user->createToken('esp32-01', ['readings:create'])->plainTextToken;
        $this->withToken($wrongAbility)->getJson('/api/fingerprint/enroll/pending')->assertForbidden();
    }
}
