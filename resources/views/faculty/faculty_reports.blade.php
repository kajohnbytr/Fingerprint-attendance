@extends('program_head.layouts.app')
@section('title', 'Faculty · Reports')
@section('heading', 'Reports')
@section('subheading', 'Attendance and enrollment reports for your assigned subjects.')
@section('content')
<div class="page-title"><div><h1>Faculty reports</h1><p>Choose up to 93 days, then download the matching records as CSV.</p></div><a class="button primary" href="{{ route('faculty.reports.export', $filters) }}">Download CSV</a></div>
@include('faculty.faculty_filters', ['report' => true, 'showStatus' => ($filters['type'] ?? 'attendance') === 'attendance'])
@if(($filters['type'] ?? 'attendance') === 'attendance')
    @include('faculty.faculty_counts')
    @include('faculty.faculty_attendance_note')
    @include('faculty.faculty_attendance_table')
@else
    <p class="faculty-muted">{{ number_format($enrollmentCount) }} enrollments created in the selected period. This date filter uses the enrollment creation date.</p>
    @include('faculty.faculty_enrollment_table')
@endif
@endsection
