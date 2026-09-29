<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_sign_in(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_leave_user_signed_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors(['email' => 'The supplied credentials are incorrect.']);

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public static function protectedPages(): array
    {
        return [['/dashboard'], ['/students'], ['/students/create'], ['/blocks'], ['/schedules']];
    }

    #[DataProvider('protectedPages')]
    public function test_guests_are_redirected_to_login(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    public function test_faculty_can_read_but_cannot_enroll_students(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'faculty']))
            ->get('/students')->assertSee('Enrolled Students')->assertDontSee('Add Student');

        $this->post('/students', Student::factory()->raw())->assertForbidden();

        $this->assertDatabaseCount('students', 0);
    }

    public function test_admin_can_enroll_view_edit_and_archive_a_student(): void
    {
        $block = Block::factory()->create();
        $payload = Student::factory()->raw([
            'block_id' => $block->id,
            'email' => 'student@phinmaed.com',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/students', $payload)->assertRedirect(route('students.index'));
        $this->assertDatabaseHas('students', $payload);
        $student = Student::where('student_number', $payload['student_number'])->firstOrFail();
        $this->get(route('students.show', $student))->assertSee($student->full_name)->assertSee($block->name);
        $this->get(route('students.edit', $student))->assertSee('Edit Student');
        $this->patch(route('students.update', $student), [...$payload, 'status' => 'graduated'])
            ->assertRedirect(route('students.index'));
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'graduated']);
        $this->delete(route('students.destroy', $student))->assertRedirect(route('students.index'));
        $this->assertSoftDeleted($student);
        $this->get(route('students.show', $student))->assertNotFound();
    }

    public function test_invalid_student_is_not_saved(): void
    {
        $payload = Student::factory()->raw(['email' => 'invalid']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/students', $payload)
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_directory_filters_records_and_escapes_content(): void
    {
        $student = Student::factory()->create(['subject' => '<script>alert(1)</script>']);
        $otherStudent = Student::factory()->create(['subject' => 'Other subject']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('students.index', ['search' => $student->student_number]))
            ->assertSee($student->student_number)
            ->assertDontSee($otherStudent->student_number)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_can_create_and_delete_an_empty_block(): void
    {
        $payload = Block::factory()->raw();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->from('/blocks')->post('/blocks', $payload)->assertRedirect('/blocks');
        $this->assertDatabaseHas('blocks', $payload);
        $block = Block::where('name', $payload['name'])->firstOrFail();
        $this->delete(route('blocks.destroy', $block))->assertRedirect();
        $this->assertModelMissing($block);
    }

    public function test_block_with_students_cannot_be_deleted(): void
    {
        $block = Block::factory()->create();
        Student::factory()->for($block)->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('blocks.destroy', $block))
            ->assertSessionHasErrors(['block' => 'Move the students before deleting this block.']);

        $this->assertModelExists($block);
    }

    public function test_schedule_times_are_saved_and_displayed_and_schedule_can_be_deleted(): void
    {
        $block = Block::factory()->create();
        $payload = Schedule::factory()->raw(['block_id' => $block->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->from('/schedules')->post('/schedules', $payload)->assertRedirect('/schedules');
        $this->assertDatabaseHas('schedules', ['subject_code' => 'IT101', 'start_time' => '08:00', 'end_time' => '09:00']);
        $this->get('/schedules')->assertSee('8:00 AM')->assertSee('9:00 AM');
        $schedule = Schedule::firstOrFail();
        $this->delete(route('schedules.destroy', $schedule))->assertRedirect();
        $this->assertModelMissing($schedule);
    }

    public function test_schedule_uses_weekday_order_and_block_filter(): void
    {
        $block = Block::factory()->create();
        $faculty = User::factory()->create(['role' => 'faculty']);
        Subject::factory()->create(['code' => 'IT101', 'faculty_id' => $faculty->id]);
        Schedule::factory()->for($block)->create(['day' => 'Friday', 'subject_name' => 'Friday subject']);
        Schedule::factory()->for($block)->create(['day' => 'Monday', 'subject_name' => 'Monday subject']);
        Schedule::factory()->create(['subject_name' => 'Other block subject']);

        $this->actingAs($faculty)
            ->get(route('schedules.index', ['block_id' => $block->id]))
            ->assertSeeInOrder(['Monday subject', 'Friday subject'])
            ->assertDontSee('Other block subject');
    }

    public function test_invalid_schedule_time_is_rejected(): void
    {
        $payload = Schedule::factory()->raw(['block_id' => Block::factory()->create()->id, 'end_time' => '07:00']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/schedules', $payload)
            ->assertSessionHasErrors(['end_time']);

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_local_seed_is_repeatable_and_creates_a_working_admin(): void
    {
        $this->seed();
        $userCount = User::count();
        $this->seed();

        $this->post('/login', ['email' => 'admin@northbridge.test', 'password' => 'ChangeMe123!'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('users', $userCount);
        $this->assertAuthenticatedAs(User::where('email', 'admin@northbridge.test')->firstOrFail());
    }
}
