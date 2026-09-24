@extends('program_head.layouts.app')
@section('title', 'My Student Profile')
@section('heading', 'My student profile')
@section('content')
@if($student)
    <div class="page-title"><div><h1>{{ $student->full_name }}</h1><p>{{ $student->student_number }} · {{ $student->block?->name ?? 'No section assigned' }}</p></div></div>
    <section class="panel"><h2>My enrolled subjects</h2><p>Contact the Program Head for enrollment or section changes.</p><div class="table-container"><table><thead><tr><th>Subject</th><th>Faculty</th><th>Day</th><th>Time</th><th>Room</th></tr></thead><tbody>
    @forelse($student->subjects as $subject)<tr><td>{{ $subject->code }} — {{ $subject->description }}</td><td>{{ $subject->faculty?->name ?? 'Unassigned' }}</td><td>{{ $subject->day }}</td><td>{{ $subject->start_time->format('g:i A') }} – {{ $subject->end_time->format('g:i A') }}</td><td>{{ $subject->room }}</td></tr>
    @empty<tr><td colspan="5" class="empty">No enrolled subjects yet.</td></tr>@endforelse
    </tbody></table></div></section>
@else
    <section class="panel"><p class="empty">No active student record is linked to this account. Contact a System Admin.</p></section>
@endif
@endsection
