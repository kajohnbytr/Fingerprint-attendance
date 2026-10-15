@extends('program_head.layouts.app')

@section('title', 'Subjects')

@section('content')

<div class="page-title">
    <div>
        <h1>Subjects</h1>

        <p>
            Fixed subjects and schedules available for faculty assignment.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="table-container">
    <table class="data-table">
        <thead>
        <tr>
            <th>Subject</th>
            <th>Units</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Assigned Faculty</th>
        </tr>
        </thead>

        <tbody>
        @forelse($subjects as $subject)
            <tr id="subject-{{ $subject->id }}">
                <td>
                    <strong>{{ $subject->code }}</strong>
                    <small>{{ $subject->description }}</small>

                    @foreach($subject->enrollments as $enrollment)
                        <small>
                            {{ $enrollment->student->full_name }}
                            · {{ $enrollment->block->name }}
                        </small>
                    @endforeach
                </td>

                <td>
                    {{ $subject->lecture_units }} lecture<br>
                    {{ $subject->laboratory_units }} laboratory
                </td>

                <td>{{ $subject->day }}</td>

                <td>
                    {{ $subject->start_time->format('g:i A') }}
                    –
                    {{ $subject->end_time->format('g:i A') }}
                </td>

                <td>{{ $subject->room }}</td>

                <td>
                    <form method="POST" action="{{ route(
                        'program-head.subjects.assign-faculty',
                        $subject
                    ) }}">
                        @csrf
                        @method('PATCH')

                        <label class="sr-only" for="faculty-{{ $subject->id }}">
                            Faculty for {{ $subject->code }}
                        </label>

                        <select
                            id="faculty-{{ $subject->id }}"
                            name="faculty_id"
                            required
                        >
                            <option value="">Select faculty</option>

                            @foreach($faculty as $member)
                                <option
                                    value="{{ $member->id }}"
                                    @selected($subject->faculty_id === $member->id)
                                >
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>

                        <button class="button primary" type="submit">
                            Assign
                        </button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="empty-message">
                    No fixed subjects are available.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection