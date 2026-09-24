@extends('program_head.layouts.app')

@section('title', 'Enroll Student')

@section('content')

<div class="page-header">
    <div>
        <h1>Enroll Student</h1>

        <p>
            Student:
            <strong>{{ $student->full_name }}</strong>
        </p>

        <p>
            Current section:
            <strong>{{ $student->block?->name ?? 'Unassigned' }}</strong>
        </p>
    </div>

    <a href="{{ route('students.index') }}">
        Back to Students
    </a>
</div>

@if($errors->any())
    <div class="alert error">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form
    method="POST"
    action="{{ route(
        'program-head.students.enroll.store',
        $student
    ) }}"
>
    @csrf

    <div class="form-card">
        <label>
            Section

            <select name="block_id" required>
                <option value="">
                    Select section
                </option>

                @foreach($blocks as $block)
                    <option
                        value="{{ $block->id }}"
                        @selected(
                            (string) old('block_id', $student->block_id)
                            === (string) $block->id
                        )
                    >
                        {{ $block->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="form-card">
        <h2>Fixed Subject List</h2>

        @foreach($subjects as $subject)
            <label class="subject-option">
                <input
                    type="checkbox"
                    name="subject_ids[]"
                    value="{{ $subject->id }}"
                    @checked(
                        in_array(
                            $subject->id,
                            $enrolledSubjectIds
                        )
                    )
                >

                <span>
                    <strong>
                        {{ $subject->code }}
                    </strong>

                    —
                    {{ $subject->description }}

                    <small>
                        {{ $subject->lecture_units }}
                        Lecture /
                        {{ $subject->laboratory_units }}
                        Laboratory

                        · {{ $subject->day }}

                        · {{
                            $subject->start_time
                                ->format('g:i A')
                        }}
                        –
                        {{
                            $subject->end_time
                                ->format('g:i A')
                        }}

                        · {{ $subject->room }}

                        · Faculty:
                        {{
                            $subject->faculty?->name
                            ?? 'Not assigned'
                        }}
                    </small>
                </span>
            </label>
        @endforeach
    </div>

    <button
        class="primary-button"
        type="submit"
    >
        Confirm Enrollment
    </button>
</form>

@endsection