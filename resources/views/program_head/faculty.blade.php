@extends('program_head.layouts.app')

@section('title', 'Faculty')

@section('content')

<div class="page-header">
    <div>
        <h1>Faculty Management</h1>

        <p>
            Add faculty names for subject assignment.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert success">
        {{ session('success') }}
    </div>
@endif

@if(auth()->user()->role !== 'faculty')
<form
    class="form-card faculty-form"
    method="POST"
    action="{{ route(
        'program-head.faculty.store'
    ) }}"
>
    @csrf

    <input
        type="text"
        name="name"
        placeholder="Faculty name"
        required
    >

    <input
        type="email"
        name="email"
        placeholder="Faculty email"
        required
    >

    <button
        class="primary-button"
        type="submit"
    >
        Add Faculty
    </button>
</form>
@endif

@foreach($faculty as $member)
    <section class="form-card faculty-card">

        <div class="faculty-heading">
            <div>
                <h2>{{ $member->name }}</h2>
                <p>{{ $member->email }}</p>
            </div>

            @if(auth()->user()->role !== 'faculty')
            <form
                method="POST"
                action="{{ route(
                    'program-head.faculty.destroy',
                    $member
                ) }}"
                onsubmit="return confirm(
                    'Remove this faculty member?'
                )"
            >
                @csrf
                @method('DELETE')

                <button
                    class="danger-button"
                    type="submit"
                >
                    Remove
                </button>
            </form>
            @endif
        </div>

        <table class="data-table">
            <thead>
            <tr>
                <th>Subject</th>
                <th>Section</th>
                <th>Day and Time</th>
                <th>Room</th>
                <th>Enrolled Students</th>
            </tr>
            </thead>

            <tbody>
            @forelse($member->assignedSubjects as $subject)
                <tr>
                    <td>
                        <strong>{{ $subject->code }}</strong>
                        <small>
                            {{ $subject->description }}
                        </small>
                    </td>

                    <td>
                        {{
                            $subject->enrollments
                                ->pluck('block.name')
                                ->filter()
                                ->unique()
                                ->join(', ')
                            ?: 'No section yet'
                        }}
                    </td>

                    <td>
                        {{ $subject->day }},
                        {{ $subject->start_time->format('g:i A') }}
                        –
                        {{ $subject->end_time->format('g:i A') }}
                    </td>

                    <td>{{ $subject->room }}</td>

                    <td>
                        <ul>
                            @forelse(
                                $subject->enrollments
                                as $enrollment
                            )
                                <li>
                                    {{
                                        $enrollment
                                            ->student
                                            ->full_name
                                    }}
                                </li>
                            @empty
                                <li>No students yet</li>
                            @endforelse
                        </ul>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        No assigned subjects.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endforeach

@endsection