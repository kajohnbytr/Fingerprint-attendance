@extends('program_head.layouts.app')

@section('title', $student->exists ? 'Edit Student' : 'Enroll Student')
@section('heading', $student->exists ? 'Edit Student' : 'Enroll Student')

@section('content')
<form class="panel form-grid" method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}">
    @csrf
    @if($student->exists)
        @method('PATCH')
    @endif

    @foreach(['student_number' => ['Student ID', 30], 'first_name' => ['First name', 80], 'last_name' => ['Last name', 80], 'email' => ['Email', 255], 'subject' => ['Subject', 100]] as $field => [$label, $maxLength])
        <label>{{ $label }}
            <input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $student->$field) }}" maxlength="{{ $maxLength }}" @required($field !== 'email' && $field !== 'subject')>
        </label>
    @endforeach

    <label>Year level
        <select name="year_level" required>
            @for($year = 1; $year <= 6; $year++)
                <option value="{{ $year }}" @selected((int) old('year_level', $student->year_level ?? 4) === $year)>Year {{ $year }}</option>
            @endfor
        </select>
    </label>
    <label>Block
        <select name="block_id">
            <option value="">Unassigned</option>
            @foreach($blocks as $block)
                <option value="{{ $block->id }}" @selected((string) old('block_id', $student->block_id) === (string) $block->id)>{{ $block->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Status
        <select name="status" required>
            @foreach(['active', 'inactive', 'graduated'] as $status)
                <option value="{{ $status }}" @selected(old('status', $student->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </label>
    <div class="form-actions">
        <button class="button primary" type="submit">Save Student</button>
        <a class="button" href="{{ route('students.index') }}">Cancel</a>
    </div>
</form>
@endsection
