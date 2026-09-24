@extends('program_head.layouts.app')
@section('title', 'System Admin · Attendance')
@section('heading', 'Attendance monitoring')
@section('content')
<div class="page-title"><div><h1>{{ $student ? $student->full_name.' · Attendance history' : 'Student attendance' }}</h1><p>{{ $filters['from'] }} through {{ $filters['to'] }}</p></div><a class="button" href="{{ route('admin.reports', $filters) }}">Open report</a></div>
@include('admin.filters', ['showStudents' => !$student])
@include('faculty.faculty_counts')
@include('faculty.faculty_attendance_note')
@include('faculty.faculty_attendance_table')
@endsection
