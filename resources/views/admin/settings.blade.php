@extends('program_head.layouts.app')
@section('title', 'System Admin · Settings')
@section('heading', 'System settings')
@section('content')
<div class="page-title"><div><h1>Academic and attendance settings</h1><p>Configure the current academic period and campus reporting defaults.</p></div></div>
<form class="panel form-grid" method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PATCH')
    <label>School year<input name="school_year" value="{{ old('school_year', $settings?->school_year) }}" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" maxlength="9"></label>
    <label>Semester<select name="semester"><option value="">Not set</option>@foreach(['First semester', 'Second semester', 'Summer'] as $semester)<option value="{{ $semester }}" @selected(old('semester', $settings?->semester) === $semester)>{{ $semester }}</option>@endforeach</select></label>
    <label>Late-arrival grace period (minutes)<input type="number" name="late_minutes" min="0" max="60" value="{{ old('late_minutes', $preferences['late_minutes']) }}" required></label>
    <label>Default reporting period<select name="report_days">@foreach([7, 30, 60, 90] as $days)<option value="{{ $days }}" @selected(old('report_days', $preferences['report_days']) == $days)>{{ $days }} days</option>@endforeach</select></label>
    <div class="form-actions"><p class="faculty-muted">Changing the grace period recalculates System Admin attendance estimates, including past dates. These settings also provide defaults for faculty without personal reporting preferences. Existing enrollments do not store academic periods; changing the school year or semester does not move or relabel historical records.</p><button class="button primary" type="submit">Save settings</button></div>
</form>
<section class="panel faculty-note"><h2>System activity log</h2><p class="faculty-muted">This application does not currently record a structured system activity log. No activity records are available to display.</p></section>
@endsection
