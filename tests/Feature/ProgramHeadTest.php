<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\ClassSchedule;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgramHeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_program_head_can_assign_a_different_schedule_to_each_faculty_block(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $mondayBlock = Block::factory()->create(['name' => 'COMSEC 01']);
        $tuesdayBlock = Block::factory()->create(['name' => 'COMSEC 02']);

        foreach ([$mondayBlock, $tuesdayBlock] as $block) {
            Enrollment::create([
                'student_id' => Student::factory()->create(['block_id' => $block->id])->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $programHead->id,
            ]);
        }

        $this->actingAs($programHead)
            ->put(route('program-head.subjects.blocks.schedule.update', [$subject, $mondayBlock]), [
                'day' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'PTC-301',
            ])->assertRedirect();

        $this->put(route('program-head.subjects.blocks.schedule.update', [$subject, $tuesdayBlock]), [
            'day' => 'Tuesday', 'start_time' => '13:00', 'end_time' => '15:00', 'room' => 'PTC-302',
        ])->assertRedirect();

        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $subject->id, 'block_id' => $mondayBlock->id, 'day' => 'Monday', 'start_time' => '08:00',
        ]);
        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $subject->id, 'block_id' => $tuesdayBlock->id, 'day' => 'Tuesday', 'start_time' => '13:00',
        ]);
    }

    public function test_program_head_can_save_multiple_class_schedules_at_once(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $firstSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $secondSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $firstBlock = Block::factory()->create();
        $secondBlock = Block::factory()->create();

        foreach ([[$firstSubject, $firstBlock], [$secondSubject, $secondBlock]] as [$subject, $block]) {
            Enrollment::create([
                'student_id' => Student::factory()->create(['block_id' => $block->id])->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $programHead->id,
            ]);
        }

        $this->actingAs($programHead)
            ->put(route('program-head.faculty.schedules.update'), [
                'schedules' => [
                    $firstSubject->id.'-'.$firstBlock->id => [
                        'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id,
                        'day' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'PTC-301',
                    ],
                    $secondSubject->id.'-'.$secondBlock->id => [
                        'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id,
                        'day' => 'Tuesday', 'start_time' => '13:00', 'end_time' => '15:00', 'room' => 'PTC-302',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 class schedules saved successfully.');

        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id, 'day' => 'Monday', 'room' => 'PTC-301',
        ]);
        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id, 'day' => 'Tuesday', 'room' => 'PTC-302',
        ]);
    }

    public function test_bulk_schedule_save_rolls_back_every_change_when_one_schedule_conflicts(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $firstSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $secondSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $firstBlock = Block::factory()->create();
        $secondBlock = Block::factory()->create();

        foreach ([[$firstSubject, $firstBlock], [$secondSubject, $secondBlock]] as [$subject, $block]) {
            Enrollment::create([
                'student_id' => Student::factory()->create(['block_id' => $block->id])->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $programHead->id,
            ]);
        }

        ClassSchedule::create([
            'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id,
            'day' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'PTC-301',
        ]);
        ClassSchedule::create([
            'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id,
            'day' => 'Tuesday', 'start_time' => '13:00', 'end_time' => '15:00', 'room' => 'PTC-302',
        ]);

        $firstKey = $firstSubject->id.'-'.$firstBlock->id;
        $this->actingAs($programHead)
            ->from(route('program-head.faculty.index'))
            ->put(route('program-head.faculty.schedules.update'), [
                'schedules' => [
                    $firstKey => [
                        'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id,
                        'day' => 'Wednesday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'PTC-401',
                    ],
                    $secondSubject->id.'-'.$secondBlock->id => [
                        'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id,
                        'day' => 'Wednesday', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'PTC-402',
                    ],
                ],
            ])
            ->assertRedirect(route('program-head.faculty.index'))
            ->assertSessionHasErrors("schedules.{$firstKey}.start_time");

        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id,
            'day' => 'Monday', 'start_time' => '08:00', 'room' => 'PTC-301',
        ]);
        $this->assertDatabaseHas('class_schedules', [
            'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id,
            'day' => 'Tuesday', 'start_time' => '13:00', 'room' => 'PTC-302',
        ]);
    }

    public function test_program_head_cannot_create_an_overlapping_faculty_schedule(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $firstSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $secondSubject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $firstBlock = Block::factory()->create();
        $secondBlock = Block::factory()->create();

        foreach ([[$firstSubject, $firstBlock], [$secondSubject, $secondBlock]] as [$subject, $block]) {
            Enrollment::create([
                'student_id' => Student::factory()->create(['block_id' => $block->id])->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $programHead->id,
            ]);
        }

        ClassSchedule::create([
            'subject_id' => $firstSubject->id, 'block_id' => $firstBlock->id, 'day' => 'Monday',
            'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'PTC-301',
        ]);

        $this->actingAs($programHead)
            ->from(route('program-head.faculty.index'))
            ->put(route('program-head.subjects.blocks.schedule.update', [$secondSubject, $secondBlock]), [
                'day' => 'Monday', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'PTC-302',
            ])
            ->assertRedirect(route('program-head.faculty.index'))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseMissing('class_schedules', [
            'subject_id' => $secondSubject->id, 'block_id' => $secondBlock->id,
        ]);
    }

    public function test_program_head_pages_render_subjects_and_faculty(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $student = Student::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'program_head']));

        $this->get('/program-head/subjects')->assertSee($subject->code)->assertSee('8:00 AM');
        $this->get(route('program-head.subjects.enroll', $subject))->assertSee($student->full_name)->assertSee($student->subject);
        $this->get('/program-head/faculty-loads')->assertSee($faculty->name)->assertSee($subject->code);
        $this->get('/students')
            ->assertOk()
            ->assertSee('All enrolled subjects');
    }

    public function test_program_head_can_filter_students_by_enrolled_subject(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $block = Block::factory()->create();
        $selectedSubject = Subject::factory()->create([
            'code' => 'ITE 310',
            'description' => 'Capstone Project and Research 2',
        ]);
        $otherSubject = Subject::factory()->create(['code' => 'ITE 367']);
        $matchingStudent = Student::factory()->create(['block_id' => $block->id]);
        $otherStudent = Student::factory()->create(['block_id' => $block->id]);

        foreach ([[$matchingStudent, $selectedSubject], [$otherStudent, $otherSubject]] as [$student, $subject]) {
            DB::table('enrollments')->insert([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'block_id' => $block->id,
                'enrolled_by' => $programHead->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($programHead)
            ->get(route('students.index', ['subject_id' => $selectedSubject->id]))
            ->assertOk()
            ->assertSee($matchingStudent->student_number)
            ->assertDontSee($otherStudent->student_number)
            ->assertSee('ITE 310 - Capstone Project and Research 2');
    }

    public function test_program_head_global_search_finds_students_faculty_and_subjects(): void
    {
        $faculty = User::factory()->create([
            'role' => 'faculty',
            'name' => 'Alex Morgan',
            'email' => 'alex.morgan@example.test',
        ]);
        $student = Student::factory()->create([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'student_number' => '2026-199999',
        ]);
        $subject = Subject::factory()->create([
            'code' => 'WNET 200',
            'description' => 'Wireless Networking',
            'faculty_id' => $faculty->id,
        ]);
        $this->actingAs(User::factory()->create(['role' => 'program_head']));

        $this->getJson(route('portal.search', ['q' => 'Jordan Lee']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'Student')
            ->assertJsonPath('results.0.title', $student->full_name)
            ->assertJsonPath('results.0.url', route('students.show', $student));
        $this->getJson(route('portal.search', ['q' => 'Alex Morgan']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'Faculty')
            ->assertJsonPath('results.0.title', $faculty->name);
        $this->getJson(route('portal.search', ['q' => 'Wireless Networking']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'Subject')
            ->assertJsonPath('results.0.url', route('program-head.subjects.index').'#subject-'.$subject->id);
    }

    public function test_faculty_global_search_only_returns_assigned_students_and_subjects(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $otherFaculty = User::factory()->create(['role' => 'faculty']);
        $ownBlock = Block::factory()->create();
        $otherBlock = Block::factory()->create();
        $ownSubject = Subject::factory()->create(['faculty_id' => $faculty->id, 'code' => 'OWN 101']);
        $otherSubject = Subject::factory()->create(['faculty_id' => $otherFaculty->id, 'code' => 'OTHER 101']);
        $ownStudent = Student::factory()->create(['first_name' => 'Morgan', 'last_name' => 'Student']);
        $otherStudent = Student::factory()->create(['first_name' => 'Morgan', 'last_name' => 'Elsewhere']);
        $ownStudent->subjects()->attach($ownSubject, ['block_id' => $ownBlock->id, 'enrolled_by' => $faculty->id]);
        $otherStudent->subjects()->attach($otherSubject, ['block_id' => $otherBlock->id, 'enrolled_by' => $otherFaculty->id]);
        $this->actingAs($faculty);

        $this->getJson(route('portal.search', ['q' => 'Morgan']))
            ->assertOk()
            ->assertJsonFragment(['title' => $ownStudent->full_name])
            ->assertJsonMissing(['title' => $otherStudent->full_name]);
        $this->getJson(route('portal.search', ['q' => 'OTHER 101']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_program_head_notifications_open_a_read_only_inbox(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $student = Student::factory()->create();
        $announcementId = DB::table('portal_announcements')->insertGetId([
            'author_id' => $faculty->id,
            'subject_id' => $subject->id,
            'title' => 'Lab schedule update',
            'body' => 'The lab has moved.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $complaintId = DB::table('student_complaints')->insertGetId([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'recorded_by' => $faculty->id,
            'title' => 'Attendance review',
            'body' => 'Please review this attendance record.',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($programHead);

        $this->getJson(route('portal.notifications'))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonFragment(['title' => 'Lab schedule update', 'url' => route('program-head.inbox').'#announcement-'.$announcementId])
            ->assertJsonFragment(['title' => 'Attendance review', 'url' => route('program-head.inbox').'#complaint-'.$complaintId]);
        $this->get(route('program-head.inbox'))
            ->assertOk()
            ->assertSee('Lab schedule update')
            ->assertSee('Attendance review')
            ->assertDontSee('Publish announcement');
    }

    public function test_program_head_reports_render_and_export_program_data(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $block = Block::factory()->create();
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $student = Student::factory()->create();
        $student->subjects()->attach($subject, ['block_id' => $block->id, 'enrolled_by' => $programHead->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($programHead)
            ->get(route('program-head.reports'))
            ->assertOk()
            ->assertSee('Academic operations reports')
            ->assertSee($faculty->name)
            ->assertSee($subject->code);

        $this->get(route('program-head.reports.export', ['type' => 'faculty_load']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_program_head_can_publish_targeted_and_archive_announcement(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $subject = Subject::factory()->create();
        $this->actingAs($programHead)
            ->post(route('program-head.announcements.store'), [
                'audience' => 'subject',
                'subject_id' => $subject->id,
                'title' => 'Capstone review',
                'body' => 'Bring the latest manuscript.',
            ])->assertRedirect();

        $announcement = DB::table('portal_announcements')->where('title', 'Capstone review')->first();
        $this->assertSame('subject', $announcement->audience);
        $this->actingAs($programHead)
            ->patch(route('program-head.announcements.archive', $announcement->id))
            ->assertRedirect();
        $this->assertNotNull(DB::table('portal_announcements')->find($announcement->id)->archived_at);
    }

    public function test_program_head_announcement_reaches_faculty_api_notifications(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $faculty = User::factory()->create(['role' => 'faculty']);

        $this->actingAs($programHead)
            ->post(route('program-head.announcements.store'), [
                'audience' => 'all',
                'title' => 'Campus attendance reminder',
                'body' => 'Please review this week’s attendance records.',
            ])
            ->assertRedirect();

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonFragment([
                'label' => 'Campus attendance reminder · Campus-wide · '.$programHead->name,
                'count' => 0,
                'title' => 'Campus attendance reminder',
                'body' => 'Please review this week’s attendance records.',
            ]);

        $this->postJson('/api/notifications/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notifications marked as read.');

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('totalCount', 0)
            ->assertJsonCount(1, 'items');
    }

    public function test_targeted_program_head_announcement_reaches_enrolled_student(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student', 'student_id' => $student->id]);
        $subject = Subject::factory()->create();
        $block = Block::factory()->create();
        $student->subjects()->attach($subject, ['block_id' => $block->id, 'enrolled_by' => $programHead->id]);
        DB::table('portal_announcements')->insert([
            'author_id' => $programHead->id,
            'subject_id' => $subject->id,
            'audience' => 'subject',
            'title' => 'Subject consultation',
            'body' => 'Consultation starts at noon.',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($studentUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Subject consultation')
            ->assertSee('Consultation starts at noon.');
    }

    public function test_program_head_can_save_scoped_settings(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head', 'password' => 'Current!Password123']);

        $this->actingAs($programHead)->patch(route('program-head.settings.update'), [
            'name' => 'Updated Program Head',
            'email' => 'head@example.test',
            'current_password' => 'Current!Password123',
            'school_year' => '2026-2027',
            'semester' => 'First semester',
            'report_days' => 7,
            'notify_announcements' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('program_head_preferences', [
            'user_id' => $programHead->id,
            'school_year' => '2026-2027',
            'report_days' => 7,
        ]);
        $this->assertSame('Updated Program Head', $programHead->fresh()->name);
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
