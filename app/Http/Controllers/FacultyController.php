<?php

namespace App\Http\Controllers;

use App\ClassScheduleManager;
use App\Models\Block;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $faculty = User::where('role', 'faculty')
            ->when(
                $request->user()->role === 'faculty',
                fn ($query) => $query->whereKey($request->user()->id)
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))
            )
            ->with([
                'assignedSubjects.enrollments.student',
                'assignedSubjects.enrollments.block',
                'assignedSubjects.classSchedules',
            ])
            ->orderBy('name')
            ->get();

        return view('program_head.faculty', [
            'faculty' => $faculty,
            'search' => $filters['search'] ?? '',
        ]);
    }

    public function updateClassSchedule(
        Request $request,
        Subject $subject,
        Block $block,
        ClassScheduleManager $schedules
    ): RedirectResponse {
        abort_unless($subject->faculty_id, 422, 'Assign a faculty member before scheduling this class.');
        abort_unless(
            $subject->enrollments()->where('block_id', $block->id)->exists(),
            404,
            'This block is not enrolled in the selected subject.'
        );

        $data = $request->validate([
            'day' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['required', 'string', 'max:100'],
        ]);

        $schedules->ensureAvailable($subject, $block, $data);

        ClassSchedule::updateOrCreate(
            ['subject_id' => $subject->id, 'block_id' => $block->id],
            $data
        );

        return back()->with('success', "Schedule saved for {$subject->code} — {$block->name}.");
    }

    public function updateClassSchedules(
        Request $request,
        ClassScheduleManager $schedules
    ): RedirectResponse {
        $data = $request->validate([
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'schedules.*.block_id' => ['required', 'integer', 'exists:blocks,id'],
            'schedules.*.day' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.room' => ['required', 'string', 'max:100'],
        ]);

        $savedCount = DB::transaction(function () use ($data, $schedules): int {
            $candidates = collect($data['schedules'])->map(function (array $candidate, string|int $key): array {
                $subject = Subject::findOrFail($candidate['subject_id']);
                $block = Block::findOrFail($candidate['block_id']);

                if (! $subject->faculty_id) {
                    throw ValidationException::withMessages([
                        "schedules.{$key}.subject_id" => 'Assign a faculty member before scheduling this class.',
                    ]);
                }

                if (! $subject->enrollments()->where('block_id', $block->id)->exists()) {
                    throw ValidationException::withMessages([
                        "schedules.{$key}.block_id" => 'This block is not enrolled in the selected subject.',
                    ]);
                }

                return [
                    'key' => $key,
                    'subject' => $subject,
                    'block' => $block,
                    'schedule' => [
                        'day' => $candidate['day'],
                        'start_time' => $candidate['start_time'],
                        'end_time' => $candidate['end_time'],
                        'room' => trim($candidate['room']),
                    ],
                ];
            });

            $duplicate = $candidates
                ->groupBy(fn (array $candidate): string => $candidate['subject']->id.'-'.$candidate['block']->id)
                ->first(fn ($group): bool => $group->count() > 1);

            if ($duplicate) {
                $candidate = $duplicate->first();
                throw ValidationException::withMessages([
                    "schedules.{$candidate['key']}.subject_id" => 'Each subject and block can only appear once.',
                ]);
            }

            foreach ($candidates as $candidate) {
                ClassSchedule::updateOrCreate(
                    [
                        'subject_id' => $candidate['subject']->id,
                        'block_id' => $candidate['block']->id,
                    ],
                    $candidate['schedule']
                );
            }

            foreach ($candidates as $candidate) {
                try {
                    $schedules->ensureAvailable(
                        $candidate['subject'],
                        $candidate['block'],
                        $candidate['schedule']
                    );
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        "schedules.{$candidate['key']}.start_time" => $exception->errors()['start_time'] ?? ['This schedule conflicts with another class.'],
                    ]);
                }
            }

            return $candidates->count();
        });

        return back()->with('success', "{$savedCount} class schedules saved successfully.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'faculty',
            'password' => Hash::make('FACULTY-2026'),
            'must_change_password' => true,
        ]);

        return back()->with(
            'success',
            'Faculty member added successfully.'
        );
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if(
            $user->role !== 'faculty',
            403
        );

        $user->delete();

        return back()->with(
            'success',
            'Faculty member removed successfully.'
        );
    }
}
