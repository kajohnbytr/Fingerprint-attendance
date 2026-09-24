<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('program_head.dashboard', [
            'studentCount' => Student::count(),

            'facultyCount' => User::where(
                'role',
                'faculty'
            )->count(),

            'blockCount' => Block::count(),

            'scheduleCount' => Schedule::count(),

            'subjectCount' => Subject::count(),

            'assignedSubjectCount' => Subject::whereNotNull('faculty_id')->count(),

            'enrollmentCount' => Enrollment::count(),

            'subjects' => Subject::query()
                ->with(['faculty', 'enrollments.block'])
                ->orderBy('code')
                ->get(),

            'recentStudents' => Student::latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
