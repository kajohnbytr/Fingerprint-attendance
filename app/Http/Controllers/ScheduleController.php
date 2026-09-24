<?php

namespace App\Http\Controllers;

use App\AttendanceSummary;
use App\Models\Block;
use App\Models\Schedule;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'block_id' => [
                'nullable',
                'integer',
                'exists:blocks,id',
            ],
        ]);

        $schedules = Schedule::with('block')
            ->when($request->user()->role === 'faculty', fn ($query) => $query
                ->whereIn('subject_code', Subject::where('faculty_id', $request->user()->id)->select('code')))
            ->when(
                $request->integer('block_id'),
                fn ($query, $blockId) => $query->where('block_id', $blockId)
            )
            ->orderByRaw(
                "CASE day WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END"
            )
            ->orderBy('start_time')
            ->paginate(15)
            ->withQueryString();

        return view('program_head.schedules', [
            'schedules' => $schedules,
            'blocks' => Block::query()
                ->when($request->user()->role === 'faculty', fn ($query) => $query->whereIn('id', app(AttendanceSummary::class)->enrollments($request->user())->select('block_id')))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        Schedule::create($this->validated($request));

        return back()->with(
            'success',
            'Schedule created successfully.'
        );
    }

    public function destroy(
        Schedule $schedule
    ): RedirectResponse {
        $schedule->delete();

        return back()->with(
            'success',
            'Schedule deleted successfully.'
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'block_id' => [
                'required',
                'integer',
                'exists:blocks,id',
            ],

            'subject_code' => [
                'required',
                'string',
                'max:30',
            ],

            'subject_name' => [
                'required',
                'string',
                'max:120',
            ],

            'day' => [
                'required',
                'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            ],

            'start_time' => [
                'required',
                'date_format:H:i',
            ],

            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],

            'room' => [
                'required',
                'string',
                'max:50',
            ],

            'instructor' => [
                'required',
                'string',
                'max:120',
            ],
        ]);
    }
}
