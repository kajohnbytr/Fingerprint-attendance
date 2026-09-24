<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        $subjects = Subject::with([
            'faculty',
            'enrollments.student',
            'enrollments.block',
        ])
            ->orderBy('code')
            ->get();

        $faculty = User::where('role', 'faculty')
            ->orderBy('name')
            ->get();

        return view('program_head.subjects', [
            'subjects' => $subjects,
            'faculty' => $faculty,
        ]);
    }

    public function assignFaculty(
        Request $request,
        Subject $subject
    ): RedirectResponse {
        $data = $request->validate([
            'faculty_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $faculty = User::whereKey($data['faculty_id'])
            ->where('role', 'faculty')
            ->firstOrFail();

        $subject->update([
            'faculty_id' => $faculty->id,
        ]);

        return back()->with(
            'success',
            'Faculty assigned successfully.'
        );
    }
}