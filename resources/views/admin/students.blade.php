@extends('program_head.layouts.app')
@section('title', 'System Admin · Students')
@section('heading', 'Students')
@section('content')
<div class="page-title"><div><h1>Student directory</h1><p>{{ $directory->total() }} existing student records. Enrollment and section changes are handled through the Program Head workflow.</p></div></div>
@include('admin.filters', ['dates' => false, 'search' => true, 'showStudents' => false, 'showStatus' => false])
<div class="table-container"><table><thead><tr><th>Student</th><th>Student number</th><th>Section</th><th>Year level</th><th>Status</th><th>Details</th></tr></thead><tbody>
@forelse($directory as $student)<tr><td>{{ $student->full_name }}</td><td>{{ $student->student_number }}</td><td>{{ $student->block?->name ?? 'Unassigned' }}</td><td>{{ $student->year_level }}</td><td>{{ ucfirst($student->status) }}</td><td><a href="{{ route('admin.students.show', $student) }}">Profile</a> · <a href="{{ route('admin.students.attendance', $student) }}">Attendance history</a></td></tr>@empty<tr><td colspan="6" class="empty">No student records match these filters.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $directory->links('pagination::simple-bootstrap-5') }}</div>
@endsection
