@extends('program_head.layouts.app')

@section('title', 'Student Profile')
@section('heading', 'Student Profile')
@section('subheading', 'Enrollment and academic information.')

@section('content')

<div class="page-title">
    <h1>{{ $student->full_name }}</h1>

    <a href="{{ route('students.index') }}">
        ← Back
    </a>
</div>

<section class="panel details-grid">
    <div>
        <small>Student ID</small>
        <strong>{{ $student->student_number }}</strong>
    </div>

    <div>
        <small>Email</small>
        <strong>{{ $student->email }}</strong>
    </div>

    <div>
        <small>Subject</small>
        <strong>{{ $student->subject }}</strong>
    </div>

    <div>
        <small>Year level</small>
        <strong>{{ $student->year_level }}</strong>
    </div>

    <div>
        <small>Block</small>
        <strong>
            {{ $student->block?->name ?? 'Unassigned' }}
        </strong>
    </div>

    <div>
        <small>Status</small>
        <strong>{{ ucfirst($student->status) }}</strong>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Assigned Subjects and Schedules</h2>
            <p>Subjects and schedules assigned to this student.</p>
        </div>

        @if(auth()->user()->role === 'program_head')
            <a class="button primary" href="{{ route('program-head.students.enroll', $student) }}">
                Enroll To
            </a>
        @endif
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
            <tr>
                <th>Subject</th>
                <th>Section</th>
                <th>Schedule</th>
                <th>Room</th>
                <th>Faculty</th>
            </tr>
            </thead>
            <tbody>
            @forelse($student->subjects as $subject)
                <tr>
                    <td>
                        <strong>{{ $subject->code }}</strong>
                        <small>{{ $subject->description }}</small>
                    </td>
                    <td>{{ $student->block?->name ?? 'Unassigned' }}</td>
                    <td>
                        {{ $subject->day }}<br>
                        {{ $subject->start_time->format('g:i A') }}
                        –
                        {{ $subject->end_time->format('g:i A') }}
                    </td>
                    <td>{{ $subject->room }}</td>
                    <td>{{ $subject->faculty?->name ?? 'Not assigned' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-message">
                        No subjects assigned yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@endsection
