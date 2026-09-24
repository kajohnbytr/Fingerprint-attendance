@extends('program_head.layouts.app')
@section('title', 'Faculty · Class Schedules')
@section('heading', 'Class Schedules')
@section('subheading', 'Teaching assignments saved by the Program Head.')
@section('content')
<div class="page-title"><div><h1>Class Schedules</h1><p>Weekly subject schedules and enrolled sections · {{ config('app.timezone') }}</p></div></div>
@include('faculty.faculty_filters', ['dates' => false, 'showStatus' => false, 'showStudents' => false])
<div class="table-container"><table>
    <thead><tr><th>Subject</th><th>Sections</th><th>Faculty</th><th>Room</th><th>Day</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($schedules as $subject)
        <tr>
            <td><strong>{{ $subject->code }}</strong><small>{{ $subject->description }}</small></td>
            <td>{{ $subject->enrollments->when($filters['block_id'] ?? null, fn ($rows, $id) => $rows->where('block_id', $id))->pluck('block.name')->filter()->unique()->join(', ') ?: 'No section enrolled' }}</td>
            <td>{{ $subject->faculty?->name }}</td><td>{{ $subject->room }}</td><td>{{ $subject->day }}</td>
            <td>{{ $subject->start_time->format('g:i A') }} – {{ $subject->end_time->format('g:i A') }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No assigned class schedules match these filters.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="pagination">{{ $schedules->links('pagination::simple-bootstrap-5') }}</div>
@endsection
