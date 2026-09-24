<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function portalPages(): array
    {
        return [
            ['/dashboard', 'Campus overview'],
            ['/schedules', 'Class Schedule'],
            ['/blocks', 'Student blocks'],
            ['/students', 'Enrolled Students'],
            ['/students/create', 'Enroll Student'],
        ];
    }

    #[DataProvider('portalPages')]
    public function test_portal_pages_render_with_navigation(string $path, string $heading): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path)
            ->assertOk()
            ->assertSee($heading, false)
            ->assertSee(route('schedules.index'), false)
            ->assertSee(route('blocks.index'), false)
            ->assertSee(route('students.index'), false);
    }

    public function test_student_directory_exposes_all_filters_and_student_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/students')
            ->assertOk()
            ->assertSee('name="subject"', false)
            ->assertSee('name="year_level"', false)
            ->assertSee('name="block_id"', false)
            ->assertSee('name="status"', false)
            ->assertSee(route('students.create'), false);
    }

    public function test_program_head_dashboard_exposes_management_workflow(): void
    {
        $subject = Subject::factory()->create([
            'code' => 'ITE 381',
            'description' => 'IT Business Solution',
            'day' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room' => 'PTC 405',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'program_head']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Program Head dashboard')
            ->assertSee($subject->code)
            ->assertSee($subject->description)
            ->assertSee('Monday')
            ->assertSee('PTC 405')
            ->assertSee(route('program-head.subjects.index'), false)
            ->assertSee(route('program-head.faculty-loads.index'), false)
            ->assertDontSee('Add Student');
    }
}
