@extends('program_head.layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="page-title">
    <div>
        <h1>
            {{ auth()->user()->role === 'program_head' ? 'Program Head dashboard' : 'Campus overview' }}
        </h1>

        <p>
            Manage subjects, students, and faculty assignments.
        </p>
    </div>

    <span class="system-status">
        ● All systems operational
    </span>
</div>

<div class="statistics">
    <article>
        <small>Active students</small>
        <strong>{{ number_format($studentCount) }}</strong>
        <span>Enrolled records</span>
    </article>

    <article>
        <small>Faculty members</small>
        <strong>{{ number_format($facultyCount) }}</strong>
        <span>Authorized users</span>
    </article>

    <article>
        <small>Active blocks</small>
        <strong>{{ number_format($blockCount) }}</strong>
        <span>Current sections</span>
    </article>

    <article>
        <small>Fixed subjects</small>
        <strong>{{ number_format($subjectCount) }}</strong>
        <span>{{ number_format($assignedSubjectCount) }} assigned · {{ number_format($enrollmentCount) }} enrollments</span>
    </article>
</div>

<div class="dashboard-grid">

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Recent enrollments</h2>
                <p>Latest students added to the portal.</p>
            </div>

            <a href="{{ route('program-head.subjects.index') }}">
                Manage subjects
            </a>
        </div>

        @forelse($recentStudents as $student)
            <div class="activity">
                <span class="activity-icon">♙</span>

                <div>
                    <strong>{{ $student->full_name }}</strong>
                    <small>{{ $student->subject }}</small>
                </div>

                <time>
                    {{ $student->created_at->diffForHumans() }}
                </time>
            </div>
        @empty
            <p class="empty">No student records yet.</p>
        @endforelse
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Program Head actions</h2>
                <p>Common tasks for managing the program.</p>
            </div>
        </div>

        <a class="quick-link" href="{{ route('program-head.subjects.index') }}">
            <span>□</span>

            <div>
                <strong>Subject assignments</strong>

                <small>
                    Assign a faculty member to each fixed subject.
                </small>
            </div>
        </a>

        <a class="quick-link" href="{{ route('program-head.faculty-loads.index') }}">
            <span>◷</span>

            <div>
                <strong>Faculty schedules</strong>

                <small>
                    Review subjects, sections, rooms, and teaching times.
                </small>
            </div>
        </a>

        <a class="quick-link" href="{{ route('students.index') }}">
            <span>◎</span>

            <div>
                <strong>Student directory</strong>

                <small>
                    View the database-managed student records.
                </small>
            </div>
        </a>
    </section>

</div>

<section class="panel dashboard-subjects">
    <div class="panel-heading">
        <div>
            <h2>Fixed subject and schedule list</h2>
            <p>Assign faculty and enroll students from each subject card.</p>
        </div>

        <a href="{{ route('program-head.faculty-loads.index') }}">
            View faculty loads
        </a>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
            <tr>
                <th>Subject</th>
                <th>Schedule</th>
                <th>Room</th>
                <th>Faculty</th>
                <th>Sections</th>
                <th>Students</th>
            </tr>
            </thead>

            <tbody>
            @forelse($subjects as $subject)
                <tr>
                    <td>
                        <strong>{{ $subject->code }}</strong>
                        <small>{{ $subject->description }}</small>
                    </td>
                    <td>
                        {{ $subject->day }}<br>
                        {{ \Carbon\Carbon::parse($subject->start_time)->format('g:i A') }}
                        –
                        {{ \Carbon\Carbon::parse($subject->end_time)->format('g:i A') }}
                    </td>
                    <td>{{ $subject->room }}</td>
                    <td>{{ $subject->faculty?->name ?? 'Unassigned' }}</td>
                    <td>
                        {{ $subject->enrollments->pluck('block.name')->filter()->unique()->join(', ') ?: 'No section' }}
                    </td>
                    <td>{{ $subject->enrollments->count() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No fixed subjects available.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@endsection
