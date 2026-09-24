<?php

namespace App\Http\Controllers;

use App\AttendanceSummary;
use App\Models\Block;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function index(Request $request): View
    {
        return view('program_head.blocks', [
            'blocks' => Block::withCount(['students' => fn ($query) => $query
                ->when($request->user()->role === 'faculty', fn ($students) => $students->whereHas('subjects', fn ($subjects) => $subjects->where('faculty_id', $request->user()->id)))])
                ->when($request->user()->role === 'faculty', fn ($query) => $query->whereIn('id', app(AttendanceSummary::class)->enrollments($request->user())->select('block_id')))
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        Block::create($this->validated($request));

        return back()->with(
            'success',
            'Block created successfully.'
        );
    }

    public function destroy(
        Block $block
    ): RedirectResponse {
        if ($block->students()->exists()) {
            return back()->withErrors([
                'block' => 'Move the students before deleting this block.',
            ]);
        }

        if (Enrollment::where('block_id', $block->id)->exists()) {
            return back()->withErrors([
                'block' => 'Move the subject enrollments before deleting this block.',
            ]);
        }

        $block->delete();

        return back()->with(
            'success',
            'Block deleted successfully.'
        );
    }

    private function validated(
        Request $request,
        ?Block $block = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('blocks')->ignore($block),
            ],

            'subject' => [
                'required',
                'string',
                'max:100',
            ],

            'year_level' => [
                'required',
                'integer',
                'between:1,6',
            ],

            'adviser' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);
    }
}
