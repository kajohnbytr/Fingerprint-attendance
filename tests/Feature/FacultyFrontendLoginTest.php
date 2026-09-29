<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FacultyFrontendLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_program_head_created_faculty_can_log_in_to_professor_portal_and_must_change_password(): void
    {
        $programHead = User::factory()->create([
            'role' => 'program_head',
            'password' => 'program-head-password',
        ]);

        $this->post('/login', [
            'email' => $programHead->email,
            'password' => 'program-head-password',
        ])->assertRedirect('/dashboard');

        $this->post('/program-head/faculty', [
            'name' => 'Faculty Member',
            'email' => 'faculty@example.test',
        ])->assertRedirect();

        $faculty = User::where('email', 'faculty@example.test')->firstOrFail();
        $this->assertSame('faculty', $faculty->role);
        $this->assertTrue($faculty->must_change_password);
        $this->assertTrue(Hash::check('FACULTY-2026', $faculty->password));

        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost:8080')->postJson('/api/auth/login', [
            'email' => $faculty->email,
            'password' => 'FACULTY-2026',
            'expectedRole' => 'admin',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.mustChangePassword', true);

        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($programHead);
    }

    public function test_faculty_can_replace_default_password_and_clear_first_login_requirement(): void
    {
        $faculty = User::factory()->create([
            'role' => 'faculty',
            'password' => 'FACULTY-2026',
            'must_change_password' => true,
        ]);

        $loginResponse = $this->withHeader('Origin', 'http://localhost:8080')->postJson('/api/auth/login', [
            'email' => $faculty->email,
            'password' => 'FACULTY-2026',
            'expectedRole' => 'admin',
        ])->assertOk();

        $this->withToken($loginResponse->json('token'))->putJson('/api/auth/password', [
            'currentPassword' => 'FACULTY-2026',
            'newPassword' => 'A-new-secure-password-2026',
            'confirmPassword' => 'A-new-secure-password-2026',
        ])->assertOk();

        $faculty->refresh();
        $this->assertFalse($faculty->must_change_password);
        $this->assertTrue(Hash::check('A-new-secure-password-2026', $faculty->password));
    }

    public function test_faculty_cannot_use_professor_api_before_replacing_temporary_password(): void
    {
        $faculty = User::factory()->create([
            'role' => 'faculty',
            'must_change_password' => true,
        ]);

        $this->actingAs($faculty)->getJson('/api/admin/overview')
            ->assertStatus(423)
            ->assertJsonPath('message', 'You must change your temporary password before continuing.');
    }

    public function test_professor_schedule_returns_only_todays_assigned_subjects_grouped_by_block(): void
    {
        Carbon::setTestNow('2026-09-29 09:00:00');
        $faculty = User::factory()->create(['role' => 'faculty']);
        $block = Block::factory()->create(['name' => 'COMSEC02']);
        $subject = Subject::factory()->create([
            'faculty_id' => $faculty->id,
            'code' => 'ITE 367',
            'description' => 'Managing IT Resources',
            'day' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);
        Subject::factory()->create([
            'faculty_id' => $faculty->id,
            'day' => 'Wednesday',
        ]);

        foreach (Student::factory()->count(2)->create(['block_id' => $block->id]) as $student) {
            Enrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $faculty->id,
            ]);
        }

        $this->actingAs($faculty)->getJson('/api/admin/schedule')
            ->assertOk()
            ->assertJsonCount(1, 'classes')
            ->assertJsonPath('classes.0.subjectCode', 'ITE 367')
            ->assertJsonPath('classes.0.subjectName', 'Managing IT Resources')
            ->assertJsonPath('classes.0.block', 'COMSEC02')
            ->assertJsonPath('classes.0.studentCount', 2);
    }

    public function test_professor_can_customize_attendance_grace_period(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $this->actingAs($faculty)->putJson('/api/admin/grace-period', [
            'graceMinutes' => 12,
        ])->assertOk()
            ->assertJsonPath('graceMinutes', 12);

        $this->assertDatabaseHas('faculty_preferences', [
            'user_id' => $faculty->id,
            'late_minutes' => 12,
        ]);
    }
}
