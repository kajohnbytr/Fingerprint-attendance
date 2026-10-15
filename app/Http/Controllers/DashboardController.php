<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        if ($request->user()->role === 'student') {
            $student = $request->user()->student?->load(['block', 'subjects.faculty']);
            $announcements = collect();
            if ($student) {
                $announcements = DB::table('portal_announcements')
                    ->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
                    ->leftJoin('blocks', 'blocks.id', '=', 'portal_announcements.block_id')
                    ->join('users', 'users.id', '=', 'portal_announcements.author_id')
                    ->where('portal_announcements.published_at', '<=', now())
                    ->whereNull('portal_announcements.archived_at')
                    ->where(fn ($query) => $query->whereNull('portal_announcements.expires_at')->orWhere('portal_announcements.expires_at', '>', now()))
                    ->where(function ($query) use ($student): void {
                        $query->whereIn('portal_announcements.audience', ['all', 'students'])
                            ->orWhere(fn ($subject) => $subject->where('portal_announcements.audience', 'subject')->whereIn('portal_announcements.subject_id', $student->subjects->pluck('id')))
                            ->orWhere(fn ($block) => $block->where('portal_announcements.audience', 'block')->where('portal_announcements.block_id', $student->block_id));
                    })
                    ->select('portal_announcements.*', 'subjects.code as subject_code', 'blocks.name as block_name', 'users.name as author_name')
                    ->orderByDesc('published_at')->limit(10)->get();
            }

            return view('students.account', compact('student', 'announcements'));
        }
        if ($request->user()->role === 'faculty') {
            return redirect()->route('faculty.dashboard');
        }

        abort_unless($request->user()->role === 'program_head', 403);

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
