<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontendApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_frontend_origin_can_request_csrf_cookie_with_credentials(): void
    {
        $this->withHeader('Origin', 'http://localhost:8080')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8080')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_spa_can_log_in_read_its_session_and_log_out(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.test',
        ]);
        $this->withHeader('Origin', 'http://localhost:8080');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'expectedRole' => 'admin',
        ])->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin');

        $token = $loginResponse->json('token');
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'capstone-frontend',
        ]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_seeded_super_admin_uses_a_temporary_password_and_must_replace_it(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $this->postJson('/api/auth/login', [
            'email' => 'superadmin@northbridge.test',
            'password' => 'SuperAdmin2026!',
            'expectedRole' => 'super_admin',
        ])->assertOk()
            ->assertJsonPath('user.role', 'super_admin')
            ->assertJsonPath('user.mustChangePassword', true);
    }

    public function test_super_admin_can_save_and_reload_school_settings(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);
        $payload = [
            'schoolName' => 'PHINMA University of Pangasinan',
            'attendanceRequired' => 80,
            'proxyDetection' => true,
            'requireAdminMfa' => false,
            'systemLockdown' => false,
            'sessionTimeoutMinutes' => 45,
            'passwordExpiryDays' => 60,
            'lowAttendanceAlerts' => true,
            'systemHealthReports' => false,
        ];

        $this->actingAs($superAdmin)->patchJson('/api/admin/settings', $payload)
            ->assertOk()
            ->assertJsonPath('settings.schoolName', 'PHINMA University of Pangasinan')
            ->assertJsonPath('settings.sessionTimeoutMinutes', 45);

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('settings.schoolName', 'PHINMA University of Pangasinan')
            ->assertJsonPath('settings.systemHealthReports', false);

        $this->getJson('/api/session-settings')
            ->assertOk()
            ->assertJsonPath('settings.timeoutMinutes', 45)
            ->assertJsonPath('settings.warningDelaySeconds', 2580)
            ->assertJsonPath('settings.countdownSeconds', 120);

        $this->assertDatabaseHas('super_admin_settings', [
            'id' => 1,
            'school_name' => 'PHINMA University of Pangasinan',
            'attendance_required' => 80,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'School settings updated']);
    }

    public function test_spa_login_rejects_inactive_accounts_and_wrong_roles(): void
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'email' => 'inactive@example.test',
        ]);
        $this->withHeader('Origin', 'http://localhost:8080')
            ->postJson('/api/auth/login', [
                'email' => $inactiveUser->email,
                'password' => 'password',
            ])->assertUnprocessable();

        $user = User::factory()->create(['role' => 'student']);
        $this->withHeader('Origin', 'http://localhost:8080')->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'expectedRole' => 'admin',
        ])->assertForbidden();

        $this->assertGuest();
    }

    public function test_student_profile_and_courses_use_the_linked_student_record(): void
    {
        $block = Block::factory()->create();
        $student = Student::factory()->create([
            'block_id' => $block->id,
            'email' => 'student@phinmaed.edu.ph',
        ]);
        $user = User::factory()->create([
            'role' => 'student',
            'student_id' => $student->id,
            'name' => 'Test Student',
        ]);
        $subject = Subject::factory()->create([
            'code' => 'IT101',
            'day' => now()->format('l'),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
            'enrolled_by' => User::factory()->create()->id,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);

        $this->actingAs($user)->getJson('/api/students/profile')
            ->assertOk()
            ->assertJsonPath('profile.email', 'student@phinmaed.edu.ph')
            ->assertJsonPath('profile.block', $block->name);

        $this->actingAs($user)->getJson('/api/students/courses')
            ->assertOk()
            ->assertJsonPath('courses.0.courseName', 'IT101 - Introduction to Computing');
    }

    public function test_student_can_update_their_name_and_password_but_not_change_their_block(): void
    {
        $block = Block::factory()->create();
        $otherBlock = Block::factory()->create();
        $student = Student::factory()->create([
            'block_id' => $block->id,
            'email' => 'student@phinmaed.com',
        ]);
        $user = User::factory()->create([
            'role' => 'student',
            'student_id' => $student->id,
            'password' => 'password',
        ]);

        $this->actingAs($user)->putJson('/api/students/profile', [
            'name' => 'Updated Student',
            'block' => $block->name,
        ])->assertOk()
            ->assertJsonPath('profile.name', 'Updated Student');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Student',
        ]);

        $this->putJson('/api/students/profile', [
            'name' => 'Updated Student',
            'block' => $otherBlock->name,
        ])->assertUnprocessable();

        $this->putJson('/api/students/password', [
            'currentPassword' => 'password',
            'newPassword' => 'New-secure-password-2026!',
            'confirmPassword' => 'New-secure-password-2026!',
        ])->assertOk();
        $this->assertTrue(Hash::check('New-secure-password-2026!', $user->fresh()->password));
    }

    public function test_student_attendance_returns_recorded_and_missed_scheduled_classes(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');
        $block = Block::factory()->create();
        $student = Student::factory()->create([
            'block_id' => $block->id,
            'email' => 'student@phinmaed.com',
        ]);
        $faculty = User::factory()->create();
        $studentUser = User::factory()->create([
            'role' => 'student',
            'student_id' => $student->id,
        ]);
        $today = now();
        $subject = Subject::factory()->create([
            'code' => 'IT201',
            'day' => $today->format('l'),
            'start_time' => $today->copy()->subHours(2)->format('H:i:s'),
            'end_time' => $today->copy()->subHour()->format('H:i:s'),
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
            'enrolled_by' => $faculty->id,
            'created_at' => $today->copy()->subDays(7),
            'updated_at' => $today->copy()->subDays(7),
        ]);
        DB::table('fingerprint_attendance_events')->insert([
            'student_id' => $student->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'subject_id' => $subject->id,
            'scanned_at' => $today->copy()->subHours(2),
            'email_sent_at' => $today->copy()->subHours(2),
            'created_at' => $today->copy()->subHours(2),
            'updated_at' => $today->copy()->subHours(2),
        ]);

        $this->actingAs($studentUser)->getJson('/api/students/attendance')
            ->assertOk()
            ->assertJsonPath('stats.presentDays', 1)
            ->assertJsonPath('records.0.status', 'Present')
            ->assertJsonPath('records.0.note', 'IT201 - Introduction to Computing');
    }

    public function test_professor_attendance_reports_return_student_statuses_as_json(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        $professor = User::factory()->create([
            'role' => 'faculty',
            'must_change_password' => false,
        ]);
        $block = Block::factory()->create([
            'name' => 'BSIT-4A',
            'year_level' => 4,
        ]);
        $student = Student::factory()->create([
            'block_id' => $block->id,
            'year_level' => 4,
        ]);
        $subject = Subject::factory()->create([
            'faculty_id' => $professor->id,
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
            'enrolled_by' => $professor->id,
            'created_at' => now()->subWeek(),
            'updated_at' => now()->subWeek(),
        ]);
        DB::table('fingerprint_attendance_events')->insert([
            'student_id' => $student->student_number,
            'device_id' => 'sensor-01',
            'fingerprint_id' => 1,
            'subject_id' => $subject->id,
            'scanned_at' => '2026-09-28 08:05:00',
            'created_at' => '2026-09-28 08:05:00',
            'updated_at' => '2026-09-28 08:05:00',
        ]);

        $this->actingAs($professor)
            ->getJson('/api/admin/reports?date=2026-09-28&block=BSIT-4A&status=present')
            ->assertOk()
            ->assertJsonPath('reports.0.date', '2026-09-28')
            ->assertJsonPath('reports.0.class', 'Year 4 / BSIT-4A')
            ->assertJsonPath('reports.0.status', 'Present')
            ->assertJsonPath('reports.0.student.idNumber', $student->student_number)
            ->assertJsonPath('blocks.0', 'BSIT-4A');
    }
}
