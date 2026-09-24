@extends('program_head.layouts.app')
@section('title', 'System Admin · Student Profile')
@section('heading', 'Student profile')
@section('content')
<div class="page-title"><div><h1>{{ $student->full_name }}</h1><p>{{ $student->student_number }}</p></div><a class="button primary" href="{{ route('admin.students.attendance', $student) }}">Attendance history</a></div>
<section class="panel details-grid"><div><small>Email</small><strong>{{ $student->email }}</strong></div><div><small>Assigned section</small><strong>{{ $student->block?->name ?? 'Unassigned' }}</strong></div><div><small>Year level</small><strong>{{ $student->year_level }}</strong></div><div><small>Program / subject</small><strong>{{ $student->subject }}</strong></div><div><small>Status</small><strong>{{ ucfirst($student->status) }}</strong></div></section>
<section class="panel faculty-note"><div class="panel-heading"><div><h2>Attendance history</h2><p>{{ $attendanceFilters['from'] }} through {{ $attendanceFilters['to'] }}</p></div><a href="{{ route('admin.students.attendance', [$student, 'from' => $attendanceFilters['from'], 'to' => $attendanceFilters['to']]) }}">Open full history</a></div><div class="statistics"><article><small>Present</small><strong>{{ $attendanceCounts['present'] }}</strong></article><article><small>Absent</small><strong>{{ $attendanceCounts['absent'] }}</strong></article><article><small>Late</small><strong>{{ $attendanceCounts['late'] }}</strong></article></div>@include('faculty.faculty_attendance_table', ['records' => $attendanceRecords, 'filters' => $attendanceFilters, 'portalPrefix' => 'admin'])</section>
<div class="page-title faculty-note"><div><h2>Enrolled subjects and schedules</h2><p>Saved by the Program Head. Subject enrollment sections are shown individually.</p></div><a href="{{ route('admin.students') }}">Back to students</a></div>
@include('faculty.faculty_enrollment_table')
@endsection
