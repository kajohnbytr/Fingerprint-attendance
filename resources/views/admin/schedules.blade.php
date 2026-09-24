@extends('program_head.layouts.app')
@section('title', 'System Admin · Schedules')
@section('heading', 'Class schedules')
@section('content')
<div class="page-title"><div><h1>Schedules and conflicts</h1><p>Both saved subject schedules and class schedules · {{ config('app.timezone') }}</p></div></div>
@include('admin.filters', ['dates' => false, 'showStudents' => false, 'showStatus' => false, 'showFaculty' => true])
<p class="faculty-muted">Conflicts compare overlapping times on the same weekday with a shared room, faculty name, or enrolled section. Adjacent classes and exact duplicate schedule entries are not conflicts. Checks use all saved schedules, including those outside the selected filters.</p>
<div class="table-container"><table><thead><tr><th>Subject</th><th>Sections</th><th>Faculty</th><th>Room</th><th>Day</th><th>Time</th><th>Conflicts</th></tr></thead><tbody>
@forelse($schedules as $row)<tr><td><strong>{{ $row['code'] }}</strong><small>{{ $row['description'] }} · {{ $row['source'] }}</small></td><td>{{ implode(', ', $row['sections']) ?: 'No section enrolled' }}</td><td>{{ $row['faculty'] ?: 'Unassigned' }}</td><td>{{ $row['room'] }}</td><td>{{ $row['day'] }}</td><td>{{ $row['start'] }} – {{ $row['end'] }}</td><td>@forelse($row['conflicts'] as $conflict)<p class="alert error">{{ $conflict }}</p>@empty<span class="badge attendance-present">No detected overlap</span>@endforelse</td></tr>@empty<tr><td colspan="7" class="empty">No schedules match these filters.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $schedules->links('pagination::simple-bootstrap-5') }}</div>
@endsection
