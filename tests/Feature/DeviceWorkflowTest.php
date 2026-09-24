<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_attendance_keeps_the_existing_readings_url_and_supports_its_own_endpoint(): void
    {
        $student = Student::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('sensor-01', ['students:create', 'attendance:create'])->plainTextToken;
        $this->withToken($token)->postJson('/api/students', [
            'device_id' => 'sensor-01', 'student_id' => $student->student_number,
            'first_name' => $student->first_name, 'last_name' => $student->last_name, 'fingerprint_id' => 1,
        ])->assertCreated();
        foreach (['/api/readings', '/api/attendance'] as $path) {
            $this->postJson($path, ['device_id' => 'sensor-01', 'fingerprint_id' => 1])
                ->assertCreated()->assertJsonPath('student_id', $student->student_number);
        }
        $this->assertDatabaseCount('fingerprint_attendance_events', 2);
        $this->assertDatabaseCount('readings', 0);
        $this->assertDatabaseCount('students', 1);
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
