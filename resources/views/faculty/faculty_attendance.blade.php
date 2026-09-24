@extends('program_head.layouts.app')
@section('title', 'Faculty · Attendance')
@section('heading', 'Attendance')
@section('subheading', 'Monitor your assigned students and their attendance history.')
@section('content')
<div class="page-title"><div><h1>{{ $student ? $student->full_name.' · Attendance history' : 'Student attendance' }}</h1><p>{{ $student?->student_number }} {{ $filters['from'] }} through {{ $filters['to'] }}</p></div><a class="button" href="{{ route('faculty.reports', $filters) }}">Open report</a></div>
@include('faculty.faculty_filters', ['showStudents' => !$student])
@include('faculty.faculty_counts')
@include('faculty.faculty_attendance_note')
@include('faculty.faculty_attendance_table')
@endsection
