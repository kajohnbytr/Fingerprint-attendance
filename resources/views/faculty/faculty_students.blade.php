@extends('program_head.layouts.app')
@section('title', 'Faculty · Enrolled Students')
@section('heading', 'Enrolled Students')
@section('subheading', 'Your subject rosters, maintained by the Program Head.')
@section('content')
<div class="page-title"><div><h1>Enrolled Students</h1><p>{{ number_format($enrollments->total()) }} saved subject enrollments. Students may be enrolled in more than one subject.</p></div></div>
@include('faculty.faculty_filters', ['dates' => false, 'showStatus' => false])
@include('faculty.faculty_enrollment_table')
@endsection
