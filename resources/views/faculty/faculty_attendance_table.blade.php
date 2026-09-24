<div class="table-container">
    <table>
        <thead><tr><th>Date</th><th>Student</th><th>Section</th><th>Subject</th><th>Class time</th><th>First scan</th><th>Inferred status</th><th>History</th></tr></thead>
        <tbody>
        @forelse($records as $record)
            @php($enrollment = $record['enrollment'])
            <tr>
                <td>{{ $record['date'] }}</td>
                <td><strong>{{ $enrollment->student->full_name }}</strong><small>{{ $enrollment->student->student_number }}</small></td>
                <td>{{ $enrollment->block?->name ?? 'Unassigned' }}</td>
                <td>{{ $enrollment->subject->code }}</td>
                <td>{{ $enrollment->subject->start_time->format('g:i A') }} – {{ $enrollment->subject->end_time->format('g:i A') }}</td>
                <td>{{ $record['scanned_at']?->format('g:i:s A') ?? 'No scan in class time' }}</td>
                <td><span class="badge attendance-{{ $record['status'] }}">{{ ucfirst($record['status']) }}</span></td>
                <td>@if(!$enrollment->student->trashed())<a href="{{ route('faculty.students.attendance', ['student' => $enrollment->student, 'from' => $filters['from'], 'to' => $filters['to']]) }}">View history</a>@else Archived @endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No attendance sessions match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $records->links('pagination::simple-bootstrap-5') }}</div>
