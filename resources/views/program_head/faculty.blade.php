@extends('program_head.layouts.app')

@section('content')
<div class="faculty-management">
<div class="page-title">
    <div>
        <span class="faculty-eyebrow">{{ auth()->user()->role === 'faculty' ? 'MY WORKSPACE' : 'PEOPLE / FACULTY' }}</span>
        <h1>{{ auth()->user()->role === 'faculty' ? 'My teaching load' : 'Faculty management' }}</h1>
        <p>{{ auth()->user()->role === 'faculty' ? 'A clear view of your current classes and enrolled sections.' : 'Add faculty and assign a schedule to every subject and block.' }}</p>
    </div>
    <span class="faculty-result-count">{{ $faculty->count() }} {{ \Illuminate\Support\Str::plural('faculty member', $faculty->count()) }}</span>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please check the schedule and try again.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if(auth()->user()->role !== 'faculty')
    <section class="panel faculty-add-panel">
        <div class="faculty-section-heading"><div><span class="faculty-eyebrow">NEW ACCOUNT</span><h2>Add a faculty member</h2><p>They’ll be asked to set a password the first time they sign in.</p></div></div>
        <form class="faculty-add-form" method="POST" action="{{ route('program-head.faculty.store') }}">
            @csrf
            <label><span>Full name</span><input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Alex Rivera" maxlength="120" autocomplete="name" required></label>
            <label><span>Work email</span><input type="email" name="email" value="{{ old('email') }}" placeholder="alex.rivera@university.edu" maxlength="255" autocomplete="email" required></label>
            <button class="button primary" type="submit">Add faculty</button>
        </form>
    </section>
@endif

@if(auth()->user()->role !== 'faculty')
    <form id="bulk-schedules-form" method="POST" action="{{ route('program-head.faculty.schedules.update') }}">
        @csrf
        @method('PUT')
    </form>
@endif

@php
    $editableScheduleCount = 0;
@endphp
<section class="faculty-directory" aria-label="{{ auth()->user()->role === 'faculty' ? 'Your teaching assignments' : 'Faculty directory' }}">
    @forelse($faculty as $member)
        @php
            $facultyClassCount = $member->assignedSubjects->sum(
                fn ($subject) => $subject->enrollments->whereNotNull('block_id')->groupBy('block_id')->count()
            );
        @endphp
        <article class="panel faculty-record" id="faculty-{{ $member->id }}">
            <header class="faculty-record-header">
                <div class="faculty-person"><span class="faculty-avatar" aria-hidden="true"></span><div><h2>{{ $member->name }}</h2><a href="mailto:{{ $member->email }}">{{ $member->email }}</a></div></div>
                <div class="faculty-record-actions">
                    <span class="faculty-assignment-count">{{ $member->assignedSubjects->count() }} {{ \Illuminate\Support\Str::plural('subject', $member->assignedSubjects->count()) }}</span>
                    @if(auth()->user()->role !== 'faculty')
                        <form method="POST" action="{{ route('program-head.faculty.destroy', $member) }}" onsubmit="return confirm('Remove this faculty member?')">@csrf @method('DELETE')<button class="faculty-remove" type="submit">Remove</button></form>
                    @endif
                </div>
            </header>

            @if($member->assignedSubjects->isNotEmpty())
                <div class="faculty-record-summary">{{ $member->assignedSubjects->sum(fn ($subject) => $subject->enrollments->count()) }} enrolled students across {{ $member->assignedSubjects->count() }} {{ \Illuminate\Support\Str::plural('subject', $member->assignedSubjects->count()) }}</div>
                <details class="faculty-schedule-collapse" @if($errors->has('schedules.*')) open @endif>
                    <summary>
                        <span><strong>{{ auth()->user()->role === 'faculty' ? 'View class schedules' : 'Edit class schedules' }}</strong><small>Subject, section, time and room assignments</small></span>
                        <span class="faculty-collapse-count">{{ $facultyClassCount }} {{ \Illuminate\Support\Str::plural('class', $facultyClassCount) }}</span>
                        <span class="faculty-collapse-chevron" aria-hidden="true"></span>
                    </summary>
                <div class="table-container faculty-load-table">
                    <table>
                        <thead><tr><th>Subject</th><th>Section</th><th>Schedule</th><th>Students</th></tr></thead>
                        <tbody>
                        @foreach($member->assignedSubjects as $subject)
                            @forelse($subject->enrollments->whereNotNull('block_id')->groupBy('block_id') as $blockEnrollments)
                                @php
                                    $block = $blockEnrollments->first()->block;
                                    $classSchedule = $subject->classSchedules->firstWhere('block_id', $block->id);
                                    $day = $classSchedule?->day ?? $subject->day;
                                    $startTime = $classSchedule?->start_time ?? $subject->start_time;
                                    $endTime = $classSchedule?->end_time ?? $subject->end_time;
                                    $room = $classSchedule?->room ?? $subject->room;
                                @endphp
                                <tr>
                                    <td><strong>{{ $subject->code }}</strong><small>{{ $subject->description }}</small></td>
                                    <td>{{ $block->name }}</td>
                                    <td>
                                        @if(auth()->user()->role === 'faculty')
                                            {{ $day }}<small>{{ $startTime->format('g:i A') }} – {{ $endTime->format('g:i A') }} · {{ $room }}</small>
                                        @else
                                            @php
                                                $scheduleKey = $subject->id.'-'.$block->id;
                                                $editableScheduleCount++;
                                            @endphp
                                            <div class="class-schedule-form">
                                                <input form="bulk-schedules-form" type="hidden" name="schedules[{{ $scheduleKey }}][subject_id]" value="{{ $subject->id }}">
                                                <input form="bulk-schedules-form" type="hidden" name="schedules[{{ $scheduleKey }}][block_id]" value="{{ $block->id }}">
                                                <label><span>Day</span><select form="bulk-schedules-form" name="schedules[{{ $scheduleKey }}][day]" required aria-invalid="{{ $errors->has("schedules.$scheduleKey.day") ? 'true' : 'false' }}">@foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $weekday)<option value="{{ $weekday }}" @selected(old("schedules.$scheduleKey.day", $day) === $weekday)>{{ $weekday }}</option>@endforeach</select>@error("schedules.$scheduleKey.day")<small class="field-error">{{ $message }}</small>@enderror</label>
                                                <label><span>Starts</span><input form="bulk-schedules-form" type="time" name="schedules[{{ $scheduleKey }}][start_time]" value="{{ old("schedules.$scheduleKey.start_time", $startTime->format('H:i')) }}" required aria-invalid="{{ $errors->has("schedules.$scheduleKey.start_time") ? 'true' : 'false' }}">@error("schedules.$scheduleKey.start_time")<small class="field-error">{{ $message }}</small>@enderror</label>
                                                <label><span>Ends</span><input form="bulk-schedules-form" type="time" name="schedules[{{ $scheduleKey }}][end_time]" value="{{ old("schedules.$scheduleKey.end_time", $endTime->format('H:i')) }}" required aria-invalid="{{ $errors->has("schedules.$scheduleKey.end_time") ? 'true' : 'false' }}">@error("schedules.$scheduleKey.end_time")<small class="field-error">{{ $message }}</small>@enderror</label>
                                                <label><span>Room</span><input form="bulk-schedules-form" type="text" name="schedules[{{ $scheduleKey }}][room]" value="{{ old("schedules.$scheduleKey.room", $room) }}" maxlength="100" required aria-invalid="{{ $errors->has("schedules.$scheduleKey.room") ? 'true' : 'false' }}">@error("schedules.$scheduleKey.room")<small class="field-error">{{ $message }}</small>@enderror</label>
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $blockEnrollments->pluck('student_id')->unique()->count() }}</td>
                                </tr>
                            @empty
                                <tr><td><strong>{{ $subject->code }}</strong><small>{{ $subject->description }}</small></td><td>No section yet</td><td>{{ $subject->day }}<small>{{ $subject->start_time->format('g:i A') }} – {{ $subject->end_time->format('g:i A') }} · {{ $subject->room }}</small></td><td>0</td></tr>
                            @endforelse
                        @endforeach
                        </tbody>
                    </table>
                </div>
                </details>
            @else
                <p class="faculty-no-assignments">No subjects assigned yet.</p>
            @endif
        </article>
    @empty
        <div class="panel faculty-empty-state"><h2>{{ $search !== '' ? 'No matching faculty' : 'No faculty accounts yet' }}</h2><p>{{ $search !== '' ? 'Try a different name or email in the search above.' : 'Add a faculty member to start assigning subjects.' }}</p></div>
    @endforelse
</section>

@if(auth()->user()->role !== 'faculty' && $editableScheduleCount > 0)
    <div class="bulk-schedule-actions" data-bulk-schedule-actions data-has-errors="{{ $errors->has('schedules.*') ? 'true' : 'false' }}">
        <p><strong>Save schedule changes</strong><span data-schedule-change-status>Review and save all {{ $editableScheduleCount }} class schedules together.</span></p>
        <button class="button primary" type="submit" form="bulk-schedules-form" data-save-schedules>Save all schedules</button>
    </div>
@endif
</div>
@endsection
