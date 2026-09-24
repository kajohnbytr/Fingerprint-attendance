@extends('program_head.layouts.app')

@section('title', 'Student Blocks')
@section('heading', 'Student Blocks')

@section('content')
<div class="page-title"><h1>Student blocks</h1></div>
@if(auth()->user()->role === 'admin')
    <form class="filters" method="POST" action="{{ route('blocks.store') }}">
        @csrf
        <label>Block name <input name="name" value="{{ old('name') }}" maxlength="50" required></label>
        <label>Subject <input name="subject" value="{{ old('subject') }}" maxlength="100" required></label>
        <label>Year level <input name="year_level" type="number" value="{{ old('year_level', 1) }}" min="1" max="6" required></label>
        <label>Adviser <input name="adviser" value="{{ old('adviser') }}" maxlength="120"></label>
        <button class="button primary" type="submit">Add Block</button>
    </form>
@endif
<div class="table-container">
    <table>
        <thead><tr><th>Block</th><th>Subject</th<th>Year</th><th>Adviser</th><th>Students</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($blocks as $block)
            <tr>
                <td>{{ $block->name }}</td><td>{{ $block->subject }}</td><td>{{ $block->year_level }}</td>
                <td>{{ $block->adviser ?? 'Unassigned' }}</td><td>{{ $block->students_count }}</td>
                <td class="actions">
                    <a href="{{ route('students.index', ['block_id' => $block->id]) }}">Students</a>
                    <a href="{{ route('schedules.index', ['block_id' => $block->id]) }}">Schedule</a>
                    @if(auth()->user()->role === 'admin')
                        <form method="POST" action="{{ route('blocks.destroy', $block) }}" onsubmit="return confirm('Delete this block and its schedules?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger-link">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No blocks yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $blocks->links() }}</div>
@endsection
