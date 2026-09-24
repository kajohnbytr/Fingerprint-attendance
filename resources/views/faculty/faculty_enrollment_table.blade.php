<div class="table-container">
    <table>
        <thead><tr><th>Student</th><th>Subject</th><th>Section</th><th>Schedule</th><th>Room</th><th>Faculty</th><th>Enrolled on</th><th>Attendance</th></tr></thead>
        <tbody>
        @forelse($enrollments as $enrollment)
            <tr>
                <td><strong>{{ $enrollment->student?->full_name ?? 'Unavailable' }}</strong><small>{{ $enrollment->student?->student_number }}{{ $enrollment->student?->trashed() ? ' · Archived' : '' }}</small></td>
                <td><strong>{{ $enrollment->subject->code }}</strong><small>{{ $enrollment->subject->description }}</small></td>
                <td>{{ $enrollment->block?->name ?? 'Unassigned' }}</td>
                <td>{{ $enrollment->subject->day }}<small>{{ $enrollment->subject->start_time->format('g:i A') }} – {{ $enrollment->subject->end_time->format('g:i A') }}</small></td>
                <td>{{ $enrollment->subject->room }}</td>
                <td>{{ $enrollment->subject->faculty?->name ?? 'Unassigned' }}</td>
                <td>{{ $enrollment->created_at->toDateString() }}</td>
                <td>@if($enrollment->student && !$enrollment->student->trashed())<a href="{{ route('faculty.students.attendance', $enrollment->student) }}">View history</a>@else Archived @endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No saved enrollments match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $enrollments->links('pagination::simple-bootstrap-5') }}</div>
