<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Block;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'subject' => [
                'nullable',
                'string',
                'max:100',
            ],

            'year_level' => [
                'nullable',
                'integer',
                'between:1,6',
            ],

            'block_id' => [
                'nullable',
                'integer',
                'exists:blocks,id',
            ],

            'status' => [
                'nullable',
                'in:active,inactive,graduated',
            ],
        ]);

        $students = Student::with(['block', 'subjects.faculty'])
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($innerQuery) use ($search) {
                        $innerQuery
                            ->where(
                                'student_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'first_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'last_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $filters['subject'] ?? null,
                fn ($query, $subject) => $query->where('subject', $subject)
            )
            ->when(
                $filters['year_level'] ?? null,
                fn ($query, $yearLevel) => $query->where('year_level', $yearLevel)
            )
            ->when(
                $filters['block_id'] ?? null,
                fn ($query, $blockId) => $query->where('block_id', $blockId)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,

            'blocks' => Block::orderBy('name')->get(),

            'subjects' => Student::select('subject')
                ->distinct()
                ->orderBy('subject')
                ->pluck('subject'),
        ]);
    }

    public function create(): View
    {
        return view('students.form', [
            'student' => new Student,
            'blocks' => Block::orderBy('name')->get(),
        ]);
    }

    public function store(
        StudentRequest $request
    ): RedirectResponse {
        Student::create($request->validated());

        return redirect()
            ->route('students.index')
            ->with('success', 'Student enrolled successfully.');
    }

    public function show(Student $student): View
    {
        return view('students.show', [
            'student' => $student->load(['block', 'subjects.faculty']),
        ]);
    }

    public function edit(Student $student): View
    {
        return view('students.form', [
            'student' => $student,
            'blocks' => Block::orderBy('name')->get(),
        ]);
    }

    public function update(
        StudentRequest $request,
        Student $student
    ): RedirectResponse {
        $student->update($request->validated());

        return redirect()
            ->route('students.index')
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(
        Student $student
    ): RedirectResponse {
        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', 'Student archived successfully.');
    }
}
