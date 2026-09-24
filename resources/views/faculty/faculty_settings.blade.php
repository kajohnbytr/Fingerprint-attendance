@extends('program_head.layouts.app')
@section('title', 'Faculty · Settings')
@section('heading', 'Settings')
@section('subheading', 'Manage your account and personal attendance reporting preferences.')
@section('content')
<div class="page-title"><div><h1>Faculty settings</h1><p>Preferences apply only to your Faculty reports. Program Head enrollments remain authoritative.</p></div></div>
<form class="panel form-grid" method="POST" action="{{ route('faculty.settings.update') }}">
    @csrf @method('PATCH')
    <label>Name<input name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="120" autocomplete="name" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" maxlength="255" autocomplete="email" required></label>
    <label>New password (optional, at least 12 characters)<input type="password" name="password" autocomplete="new-password" minlength="12"></label>
    <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password"></label>
    <label>Late-arrival grace period (minutes)<input type="number" name="late_minutes" min="0" max="60" value="{{ old('late_minutes', $preferences['late_minutes']) }}" required><small>Changing this recalculates your inferred attendance, including past dates.</small></label>
    <label>Default report period<select name="report_days">@foreach([7, 30, 60, 90] as $days)<option value="{{ $days }}" @selected(old('report_days', $preferences['report_days']) == $days)>{{ $days }} days</option>@endforeach</select></label>
    <label>Current password to save changes<input type="password" name="current_password" autocomplete="current-password" required></label>
    <div><p class="faculty-muted">Role: Faculty<br>Schedule timezone: {{ config('app.timezone') }}<br>Contact the Program Head for changes to teaching assignments or rosters.</p></div>
    <div class="form-actions"><button type="submit" class="button primary">Save settings</button></div>
</form>
@endsection
