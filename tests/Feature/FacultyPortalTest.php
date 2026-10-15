<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FacultyPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function pages(): array
    {
        return [['dashboard'], ['attendance'], ['students'], ['schedules'], ['reports'], ['settings'], ['inbox']];
    }

    #[DataProvider('pages')]
    public function test_faculty_pages_render_empty_states_and_reject_other_roles(string $page): void
    {
        $this->get(route('faculty.'.$page))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'program_head']))
            ->get(route('faculty.'.$page))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'faculty']))
            ->get(route('faculty.'.$page))->assertOk()->assertSee('FACULTY PORTAL');
    }

    public function test_dashboard_uses_saved_scans_and_deduplicates_per_student_class(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(12, 0));
        $faculty = User::factory()->create(['role' => 'faculty']);
        [$present, $subject, $block] = $this->enroll($faculty);
        [$late] = $this->enroll($faculty, $subject, $block);
        [$absent] = $this->enroll($faculty, $subject, $block);
        $this->scan($present, '2026-09-21 08:15:00');
        $this->scan($present, '2026-09-21 08:35:00');
        $this->scan($late, '2026-09-21 08:15:01');
        $this->scan($absent, '2026-09-21 10:00:00');

        $this->actingAs($faculty)->get(route('faculty.dashboard', $this->dates()))
            ->assertOk()->assertViewHas('studentCount', 3)
            ->assertViewHas('counts', ['present' => 1, 'absent' => 1, 'late' => 1, 'pending' => 0])
            ->assertSee($absent->full_name)->assertSee($late->full_name);
        $this->get('/dashboard')->assertRedirect(route('faculty.dashboard'));
        $this->assertDatabaseCount('fingerprint_attendance_events', 4);
    }

    public function test_ongoing_future_and_pre_enrollment_classes_are_not_absent(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(8, 5));
        $faculty = User::factory()->create(['role' => 'faculty']);
        $this->enroll($faculty);
        $futureSubject = Subject::factory()->create(['faculty_id' => $faculty->id, 'start_time' => '13:00', 'end_time' => '14:00']);
        $this->enroll($faculty, $futureSubject);
        [$newStudent, $subject] = $this->enroll($faculty);
        DB::table('enrollments')->where('student_id', $newStudent->id)->update(['created_at' => now()]);

        $this->actingAs($faculty)->get(route('faculty.attendance', $this->dates()))
            ->assertOk()->assertViewHas('counts', ['present' => 0, 'absent' => 0, 'late' => 0, 'pending' => 1]);
        $this->get(route('faculty.attendance', ['from' => '2026-09-14', 'to' => '2026-09-14']))
            ->assertOk()->assertViewHas('counts', ['present' => 0, 'absent' => 0, 'late' => 0, 'pending' => 0]);
    }

    public function test_every_data_page_and_export_is_scoped_to_faculty(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(12, 0));
        $faculty = User::factory()->create(['role' => 'faculty']);
        [$own, $ownSubject, $ownBlock] = $this->enroll($faculty);
        [$other, $otherSubject, $otherBlock] = $this->enroll(User::factory()->create(['role' => 'faculty']));
        $this->actingAs($faculty);

        foreach (['dashboard', 'attendance', 'students', 'schedules', 'reports', 'inbox'] as $page) {
            $this->get(route('faculty.'.$page, $this->dates()))->assertOk()
                ->assertDontSee($other->student_number)->assertDontSee($otherSubject->code)->assertDontSee($otherBlock->name);
        }
        $this->get(route('faculty.students'))->assertSee($own->student_number)->assertSee($ownSubject->code)->assertSee($ownBlock->name);
        $this->get(route('faculty.students.attendance', ['student' => $other, ...$this->dates()]))->assertNotFound();
        $this->get(route('students.show', $other))->assertNotFound();
        $this->get(route('students.index'))->assertDontSee($other->student_number);
        $this->get(route('blocks.index'))->assertDontSee($otherBlock->name);
        $this->get(route('faculty.attendance', [...$this->dates(), 'student_id' => $other->id]))
            ->assertViewHas('counts', ['present' => 0, 'absent' => 0, 'late' => 0, 'pending' => 0]);
        $response = $this->get(route('faculty.reports.export', $this->dates()))->assertOk()->assertDownload();
        $this->assertStringContainsString($own->student_number, $response->streamedContent());
        $this->assertStringNotContainsString($other->student_number, $response->streamedContent());
    }

    public function test_attendance_filters_and_history_return_matching_sessions(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(12, 0));
        $faculty = User::factory()->create(['role' => 'faculty']);
        [$student, $subject, $block] = $this->enroll($faculty);
        $this->enroll($faculty, $subject, $block);
        $this->scan($student, '2026-09-21 08:30:00');
        $filters = [...$this->dates(), 'student_id' => $student->id, 'subject_id' => $subject->id, 'block_id' => $block->id, 'status' => 'late'];

        $this->actingAs($faculty)->get(route('faculty.attendance', $filters))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('counts', ['present' => 0, 'absent' => 0, 'late' => 1, 'pending' => 0]);
        $this->get(route('faculty.students.attendance', ['student' => $student, ...$this->dates()]))
            ->assertOk()->assertSee($student->full_name)->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->get(route('faculty.attendance', [...$filters, 'status' => 'present']))->assertViewHas('records', fn ($rows) => $rows->total() === 0);
    }

    public function test_enrollment_reports_filter_creation_dates_and_escape_csv_formulas(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(12, 0));
        $faculty = User::factory()->create(['role' => 'faculty']);
        [$student, $subject, $block] = $this->enroll($faculty);
        $student->update(['first_name' => '=SUM(1+1)']);
        $this->actingAs($faculty);
        $filters = ['from' => '2026-09-20', 'to' => '2026-09-21', 'type' => 'enrollment', 'block_id' => $block->id];
        $this->get(route('faculty.reports', $filters))->assertOk()->assertViewHas('enrollmentCount', 1);
        $this->get(route('faculty.reports', [...$filters, 'from' => '2026-09-21']))->assertViewHas('enrollmentCount', 0);
        $response = $this->get(route('faculty.reports.export', $filters))->assertDownload();
        $this->assertStringContainsString("'=SUM(1+1)", $response->streamedContent());
        $this->assertStringContainsString($subject->code, $response->streamedContent());
    }

    public function test_announcements_and_complaints_are_saved_displayed_and_authorized(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        [$student, $subject] = $this->enroll($faculty);
        [$otherStudent, $otherSubject] = $this->enroll(User::factory()->create(['role' => 'faculty']));
        $this->actingAs($faculty);
        $announcement = ['subject_id' => $subject->id, 'title' => 'Room update', 'body' => '<script>alert(1)</script>'];
        $this->post(route('faculty.announcements.store'), $announcement)->assertRedirect();
        $this->post(route('faculty.announcements.store'), [...$announcement, 'subject_id' => $otherSubject->id])->assertNotFound();
        $complaint = ['student_id' => $student->id, 'subject_id' => $subject->id, 'title' => 'Attendance concern', 'body' => 'Please review my scan.'];
        $this->post(route('faculty.complaints.store'), $complaint)->assertRedirect();
        $this->post(route('faculty.complaints.store'), [...$complaint, 'student_id' => $otherStudent->id])->assertNotFound();
        $this->get(route('faculty.dashboard'))->assertSee('Room update')->assertSee('Attendance concern')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $id = DB::table('student_complaints')->value('id');
        $this->patch(route('faculty.complaints.update', $id), ['status' => 'resolved'])->assertRedirect();
        $this->get(route('faculty.dashboard'))->assertDontSee('Attendance concern');
        $this->get(route('faculty.inbox'))->assertSee('Attendance concern');
        $this->actingAs(User::findOrFail($otherSubject->faculty_id))
            ->patch(route('faculty.complaints.update', $id), ['status' => 'open'])->assertNotFound();
        $this->assertDatabaseHas('student_complaints', ['id' => $id, 'status' => 'resolved']);
    }

    public function test_settings_require_current_password_and_cannot_change_role(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $this->actingAs($faculty);
        $payload = ['name' => 'Updated Faculty', 'email' => 'faculty-updated@example.test',
            'current_password' => 'wrong', 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!',
            'late_minutes' => 10, 'report_days' => 7, 'role' => 'admin'];
        $this->patch(route('faculty.settings.update'), $payload)->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('faculty_preferences', 0);
        $this->patch(route('faculty.settings.update'), [...$payload, 'current_password' => 'password'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('faculty', $faculty->fresh()->role);
        $this->assertSame('Updated Faculty', $faculty->fresh()->name);
        $this->assertTrue(Hash::check('New-password-2026!', $faculty->fresh()->password));
        $this->assertDatabaseHas('faculty_preferences', ['user_id' => $faculty->id, 'late_minutes' => 10, 'report_days' => 7]);
    }

    public function test_bad_filters_are_rejected_and_pagination_preserves_filters(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(12, 0));
        $faculty = User::factory()->create(['role' => 'faculty']);
        $this->actingAs($faculty);
        $this->get(route('faculty.attendance', ['from' => '2025-01-01', 'to' => '2026-09-21']))->assertSessionHasErrors('from');
        $this->get(route('faculty.attendance', ['from' => 'invalid', 'to' => '2026-09-21']))->assertSessionHasErrors('from');
        $this->get(route('faculty.attendance', ['from' => '2026-09-21', 'to' => '2026-09-22']))->assertSessionHasErrors('to');
        $this->get(route('faculty.attendance', [...$this->dates(), 'status' => 'unknown']))->assertSessionHasErrors('status');
        [$student, $subject, $block] = $this->enroll($faculty);
        for ($index = 0; $index < 20; $index++) {
            $this->enroll($faculty, $subject, $block);
        }
        $this->get(route('faculty.attendance', [...$this->dates(), 'block_id' => $block->id, 'page' => 2]))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 21 && $rows->count() === 1)
            ->assertSee('block_id='.$block->id);
    }

    public function test_program_head_enrollment_immediately_appears_without_modifying_student(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['faculty_id' => $faculty->id]);
        $student = Student::factory()->create();
        $block = Block::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'program_head']))
            ->post(route('program-head.students.enroll.store', $student), ['block_id' => $block->id, 'subject_ids' => [$subject->id]])
            ->assertRedirect();
        $before = $student->fresh()->getAttributes();
        $this->actingAs($faculty)->get(route('faculty.students'))->assertOk()->assertSee($student->student_number)->assertSee($subject->code);
        $this->get(route('faculty.schedules'))->assertSee($subject->room)->assertSee($block->name);
        $this->assertSame($before, $student->fresh()->getAttributes());
        $this->assertDatabaseCount('enrollments', 1);
    }

    /** @return array{0: Student, 1: Subject, 2: Block} */
    private function enroll(User $faculty, ?Subject $subject = null, ?Block $block = null): array
    {
        $subject ??= Subject::factory()->create(['faculty_id' => $faculty->id]);
        $block ??= Block::factory()->create();
        $student = Student::factory()->for($block)->create();
        $student->subjects()->attach($subject, ['block_id' => $block->id, 'enrolled_by' => $faculty->id,
            'created_at' => now()->subDay()->startOfDay(), 'updated_at' => now()->subDay()->startOfDay()]);

        return [$student, $subject, $block];
    }

    private function scan(Student $student, string $time): void
    {
        DB::table('fingerprint_attendance_events')->insert([
            'student_id' => $student->student_number, 'device_id' => 'test-device', 'fingerprint_id' => $student->id,
            'scanned_at' => $time, 'created_at' => $time, 'updated_at' => $time,
        ]);
    }

    /** @return array{from: string, to: string} */
    private function dates(): array
    {
        return ['from' => '2026-09-21', 'to' => '2026-09-21'];
    }
}
