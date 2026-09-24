<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgramHeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_program_head_pages_render_subjects_and_faculty(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $student = Student::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'program_head']));

        $this->get('/program-head/subjects')->assertSee($subject->code)->assertSee('8:00 AM');
        $this->get(route('program-head.subjects.enroll', $subject))->assertSee($student->full_name)->assertSee($student->subject);
        $this->get('/program-head/faculty-loads')->assertSee($faculty->name)->assertSee($subject->code);
        $this->get('/students')->assertOk();
    }

    public function test_faculty_only_sees_own_load_and_cannot_assign_subjects(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $otherFaculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $otherSubject = Subject::factory()->create(['faculty_id' => $otherFaculty->id]);
        $this->actingAs($faculty);

        $this->get('/program-head/faculty-loads')->assertSee($subject->code)->assertDontSee($otherSubject->code);
        $this->patch(route('program-head.subjects.assign-faculty', $subject), ['faculty_id' => $otherFaculty->id])->assertForbidden();
        $this->assertSame($faculty->id, $subject->fresh()->faculty_id);
    }

    public function test_admin_can_assign_faculty(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('program-head.subjects.assign-faculty', $subject), ['faculty_id' => $faculty->id])->assertRedirect();

        $this->assertSame($faculty->id, $subject->fresh()->faculty_id);
    }

    public function test_non_faculty_assignment_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();

        $this->actingAs($admin)->patch(route('program-head.subjects.assign-faculty', $subject), ['faculty_id' => $admin->id])->assertNotFound();

        $this->assertNull($subject->fresh()->faculty_id);
    }

    public function test_enrollment_is_saved_without_duplicates_and_archived_students_render(): void
    {
        $subject = Subject::factory()->create();
        $student = Student::factory()->create();
        $block = Block::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $payload = ['block_id' => $block->id, 'student_ids' => [$student->id]];

        $this->post(route('program-head.subjects.enroll.store', $subject), $payload)->assertRedirect(route('program-head.subjects.index'));
        $this->post(route('program-head.subjects.enroll.store', $subject), $payload)->assertRedirect();
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'subject_id' => $subject->id, 'block_id' => $block->id, 'enrolled_by' => $admin->id]);
        $this->assertSame($block->id, $student->fresh()->block_id);
        $student->delete();
        $this->get('/program-head/subjects')->assertSee($student->full_name)->assertSee($block->name);
    }

    public function test_program_head_assigns_student_section_and_subjects(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::factory()->create([
            'code' => 'ITE 381',
            'day' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);
        $block = Block::factory()->create(['name' => 'COMSEC 02']);
        $programHead = User::factory()->create(['role' => 'program_head']);

        $this->actingAs($programHead)
            ->get(route('program-head.students.enroll', $student))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee($subject->code)
            ->assertSee('Monday')
            ->assertSee('10:00 AM')
            ->assertDontSee('Add Student')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');

        $this->get(route('students.index'))
            ->assertSee('Unassigned')
            ->assertDontSee('Add Student')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');

        $this->post(
            route('program-head.students.enroll.store', $student),
            [
                'block_id' => $block->id,
                'subject_ids' => [$subject->id],
            ]
        )->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'block_id' => $block->id,
            'enrolled_by' => $programHead->id,
        ]);
        $this->assertSame($block->id, $student->fresh()->block_id);

        $this->get(route('students.index'))
            ->assertSee($subject->code)
            ->assertSee($block->name)
            ->assertSee('Enroll To');
    }

    public static function ineligibleStudents(): array
    {
        return ['inactive' => ['inactive', false], 'graduated' => ['graduated', false], 'archived' => ['active', true]];
    }

    #[DataProvider('ineligibleStudents')]
    public function test_ineligible_students_cannot_be_enrolled(string $status, bool $archived): void
    {
        $student = Student::factory()->create(['status' => $status]);
        if ($archived) {
            $student->delete();
        }
        $subject = Subject::factory()->create();
        $block = Block::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('program-head.subjects.enroll.store', $subject), ['block_id' => $block->id, 'student_ids' => [$student->id]])
            ->assertSessionHasErrors('student_ids.0');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_block_used_by_subject_enrollment_cannot_be_deleted(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();
        $block = Block::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('program-head.subjects.enroll.store', $subject), ['block_id' => $block->id, 'student_ids' => [$student->id]])->assertRedirect();
        $student->delete();

        $this->delete(route('blocks.destroy', $block))->assertSessionHasErrors('block');

        $this->assertModelExists($block);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_guests_cannot_access_program_head_pages(): void
    {
        $this->get('/program-head/subjects')->assertRedirect(route('login'));
        $this->get('/program-head/faculty-loads')->assertRedirect(route('login'));
    }
}
