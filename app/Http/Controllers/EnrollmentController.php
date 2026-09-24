<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function create(Student $student): View
    {
        $student->load('subjects');

        $subjects = Subject::with('faculty')
            ->orderBy('code')
            ->get();

        $blocks = Block::orderBy('name')->get();

        $enrolledSubjectIds = $student->subjects
            ->pluck('id')
            ->all();

        return view('program_head.enroll_students', [
            'student' => $student,
            'subjects' => $subjects,
            'blocks' => $blocks,
            'enrolledSubjectIds' => $enrolledSubjectIds,
        ]);
    }

    public function store(
        Request $request,
        Student $student
    ): RedirectResponse {
        $data = $request->validate([
            'block_id' => [
                'required',
                'integer',
                'exists:blocks,id',
            ],

            'subject_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'subject_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id'),
            ],
        ]);

        DB::transaction(function () use (
            $data,
            $student,
            $request
        ) {
            $student->update([
                'block_id' => $data['block_id'],
            ]);

            $student->subjects()->syncWithPivotValues(
                $data['subject_ids'],
                [
                    'block_id' => $data['block_id'],
                    'enrolled_by' => $request->user()->id,
                ]
            );
        });

        return redirect()
            ->route('students.index')
            ->with(
                'success',
                'Student subjects enrolled successfully.'
            );
    }

    public function createForSubject(Subject $subject): View
    {
        $subject->load(['faculty', 'enrollments']);

        return view('program_head.enroll_subject', [
            'subject' => $subject,
            'students' => Student::where('status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'blocks' => Block::orderBy('name')->get(),
            'enrolledStudentIds' => $subject->enrollments
                ->pluck('student_id')
                ->all(),
        ]);
    }

    public function storeForSubject(
        Request $request,
        Subject $subject
    ): RedirectResponse {
        $data = $request->validate([
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('students', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
        ]);

        DB::transaction(function () use ($data, $subject, $request): void {
            foreach ($data['student_ids'] as $studentId) {
                $student = Student::findOrFail($studentId);

                Enrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'block_id' => $data['block_id'],
                        'enrolled_by' => $request->user()->id,
                    ]
                );

                $student->update([
                    'block_id' => $data['block_id'],
                ]);
            }
        });

        return redirect()->route('program-head.subjects.index');
    }
}
