<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_tabs_receive_authenticated_json_from_the_database(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'IoT attendance recorded',
            'details' => 'Device attendance-01 recorded a scan.',
            'status' => 'Success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('systemStats.totalUsers', 1)
            ->assertJsonPath('auditLogs.0.action', 'IoT attendance recorded');

        foreach ([
            '/api/admin/master-schedule' => 'entries',
            '/api/admin/users' => 'users',
            '/api/admin/available-blocks' => 'blocks',
            '/api/admin/schedule-change-requests' => 'requests',
            '/api/admin/audit-logs' => 'logs',
            '/api/admin/app-reports' => 'reports',
        ] as $endpoint => $key) {
            $this->getJson($endpoint)->assertOk()->assertJsonStructure([$key]);
        }
    }

    public function test_super_admin_can_create_a_database_backed_master_schedule_entry(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);

        $this->actingAs($superAdmin)->postJson('/api/admin/master-schedule', [
            'block' => 'BSIT-4A',
            'courseName' => 'ITE 401 - Platform Technologies',
            'schedule' => 'Monday 9:00 AM - 10:30 AM',
            'room' => 'Lab 3',
            'graceOverrideMinutes' => 10,
            'order' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('master_schedule_entries', [
            'block' => 'BSIT-4A',
            'course_name' => 'ITE 401 - Platform Technologies',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'Master schedule created',
        ]);
    }
}
