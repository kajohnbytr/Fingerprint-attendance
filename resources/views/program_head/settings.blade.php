@extends('program_head.layouts.app')
@section('title', 'Program Head · Settings')
@section('heading', 'Program settings')
@section('content')
<div class="page-title"><div><h1>Profile and program preferences</h1><p>These settings affect your Program Head workspace only.</p></div></div>
<form class="panel form-grid" method="POST" action="{{ route('program-head.settings.update') }}">@csrf @method('PATCH')
    <label>Name<input name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="120" required></label><label>Email<input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></label>
    <label>School year<input name="school_year" value="{{ old('school_year', $preferences?->school_year ?? $system?->school_year) }}" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" maxlength="9"></label><label>Semester<select name="semester"><option value="">Use system default</option>@foreach(['First semester', 'Second semester', 'Summer'] as $semester)<option value="{{ $semester }}" @selected(old('semester', $preferences?->semester ?? $system?->semester) === $semester)>{{ $semester }}</option>@endforeach</select></label>
    <label>Default report period<select name="report_days">@foreach([7, 30, 60, 90] as $days)<option value="{{ $days }}" @selected(old('report_days', $preferences?->report_days ?? 7) == $days)>{{ $days }} days</option>@endforeach</select></label>
    <fieldset><legend>Notifications</legend><label><input type="checkbox" name="notify_announcements" value="1" @checked(old('notify_announcements', $preferences?->notify_announcements ?? true))> Announcements</label><label><input type="checkbox" name="notify_complaints" value="1" @checked(old('notify_complaints', $preferences?->notify_complaints ?? true))> Student complaints</label><label><input type="checkbox" name="notify_schedule_changes" value="1" @checked(old('notify_schedule_changes', $preferences?->notify_schedule_changes ?? true))> Schedule changes</label></fieldset>
    <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label>New password (optional)<input type="password" name="password" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password"></label>
    <div class="form-actions"><p class="faculty-muted">Device tokens, security policies, mail credentials, and global attendance rules remain controlled by the System Admin.</p><button class="button primary" type="submit">Save settings</button></div>
</form>
@endsection
