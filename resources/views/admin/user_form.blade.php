@extends('program_head.layouts.app')
@section('title', 'System Admin · Manage Account')
@section('heading', $account->exists ? 'Manage account' : 'Create account')
@section('content')
<div class="page-title"><div><h1>{{ $account->exists ? 'Edit '.$account->name : 'Create an account' }}</h1><p>Student accounts link to existing student records. Role changes do not enroll students.</p></div><a href="{{ route('admin.users') }}">Back to users</a></div>
<form class="panel form-grid" method="POST" action="{{ $account->exists ? route('admin.users.update', $account) : route('admin.users.store') }}">@csrf @if($account->exists) @method('PATCH') @endif
    <label>Name<input name="name" value="{{ old('name', $account->name) }}" maxlength="120" autocomplete="name" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $account->email) }}" maxlength="255" autocomplete="email" required></label>
    <label>Role<select name="role" required>@foreach(['admin' => 'System Admin', 'program_head' => 'Program Head', 'faculty' => 'Faculty', 'student' => 'Student'] as $role => $label)<option value="{{ $role }}" @selected(old('role', $account->role) === $role)>{{ $label }}</option>@endforeach</select></label>
    <label>Account status<select name="is_active"><option value="1" @selected(old('is_active', $account->is_active) == 1)>Active</option><option value="0" @selected(old('is_active', $account->is_active) == 0)>Inactive</option></select></label>
    <label>Linked student (required only for Student role)<select name="student_id"><option value="">No linked student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id', $account->student_id) == $student->id)>{{ $student->student_number }} · {{ $student->full_name }}</option>@endforeach</select></label>
    <div><p class="faculty-muted">Keep at least one active System Admin. Reassign a faculty member’s subjects before changing their role. Deactivation blocks login and revokes API tokens.</p></div>
    <label>{{ $account->exists ? 'New password (leave blank to keep current)' : 'Password' }}<input type="password" name="password" minlength="12" autocomplete="new-password" @required(!$account->exists)></label>
    <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" @required(!$account->exists)></label>
    <div class="form-actions"><button class="button primary" type="submit">{{ $account->exists ? 'Save account' : 'Create account' }}</button></div>
</form>
@endsection
