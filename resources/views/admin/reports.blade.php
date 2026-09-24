@extends('program_head.layouts.app')
@section('title', 'System Admin · Reports')
@section('heading', 'Attendance and enrollment reports')
@section('content')
<div class="page-title"><div><h1>Campus reports</h1><p>Choose up to 93 days and export the matching records as CSV.</p></div><a class="button primary" href="{{ route('admin.reports.export', $filters) }}">Download CSV</a></div>
@include('admin.filters', ['report' => true, 'showStatus' => ($filters['type'] ?? 'attendance') === 'attendance'])
@if(($filters['type'] ?? 'attendance') === 'attendance')
    @include('faculty.faculty_counts')
    @include('faculty.faculty_attendance_note')
    @include('faculty.faculty_attendance_table')
    @include('admin.rankings')
@else
    <p class="faculty-muted">{{ $enrollmentCount }} subject enrollments created in this period. Dates filter enrollment creation, not class meetings. Existing records do not store historical semester assignments.</p>
    @include('faculty.faculty_enrollment_table')
@endif
@endsection
