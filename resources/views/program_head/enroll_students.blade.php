@extends('program_head.layouts.app')

@section('title', 'Enroll Student')

@section('content')
<div class="enroll-page">
    <header class="enroll-header">
        <div>
            <span class="enroll-eyebrow">Student management</span>
            <h1>Enroll student</h1>
            <p>Assign a section and confirm the subjects for this student.</p>
        </div>
        <a class="enroll-back" href="{{ route('students.index') }}">&larr; Back to students</a>
    </header>

    <div class="enroll-student-card">
        <div class="enroll-avatar" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</div>
        <div><span>Student</span><strong>{{ $student->full_name }}</strong></div>
        <div><span>Student ID</span><strong>{{ $student->student_number }}</strong></div>
        <div><span>Current section</span><strong>{{ $student->block?->name ?? 'Unassigned' }}</strong></div>
    </div>

    @if($errors->any())
        <div class="alert error enroll-errors" role="alert">
            <strong>Please review the enrollment details.</strong>
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('program-head.students.enroll.store', $student) }}">
        @csrf

        <section class="enroll-panel">
            <div class="enroll-panel-heading">
                <span class="enroll-step">1</span>
                <div><h2>Select section</h2><p>The section determines the available fixed subject list.</p></div>
            </div>
            <label class="enroll-field" for="block_id">
                <span>Section</span>
                <select id="block_id" name="block_id" required>
                    <option value="">Select section</option>
                    @foreach($blocks as $block)
                        <option value="{{ $block->id }}" @selected((string) old('block_id', $student->block_id) === (string) $block->id)>{{ $block->name }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        <section class="enroll-panel">
            <div class="enroll-panel-heading">
                <span class="enroll-step">2</span>
                <div><h2>Confirm subjects</h2><p>Select every subject the student should attend.</p></div>
                <span class="enroll-count">{{ $subjects->count() }} available</span>
            </div>

            <div class="enroll-subjects">
                @forelse($subjects as $subject)
                    <label class="enroll-subject">
                        <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked(in_array($subject->id, old('subject_ids', $enrolledSubjectIds)))>
                        <span class="enroll-check" aria-hidden="true">&#10003;</span>
                        <span class="enroll-subject-copy">
                            <span class="enroll-subject-title"><strong>{{ $subject->code }}</strong><span>{{ $subject->description }}</span></span>
                            <small>{{ $subject->day }} &middot; {{ $subject->start_time->format('g:i A') }}&ndash;{{ $subject->end_time->format('g:i A') }} &middot; {{ $subject->room }} &middot; {{ $subject->faculty?->name ?? 'Faculty not assigned' }}</small>
                            <small>{{ $subject->lecture_units }} lecture / {{ $subject->laboratory_units }} laboratory units</small>
                        </span>
                    </label>
                @empty
                    <div class="enroll-empty">No subjects are available for enrollment.</div>
                @endforelse
            </div>
        </section>

        <div class="enroll-actions">
            <a class="enroll-cancel" href="{{ route('students.index') }}">Cancel</a>
            <button class="primary-button" type="submit">Confirm enrollment</button>
        </div>
    </form>
</div>
@endsection
