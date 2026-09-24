@extends('program_head.layouts.app')

@section('title', 'Enroll Students')

@section('content')

<div class="page-title">
    <div>
        <h1>Enroll Students</h1>
        <p>{{ $subject->code }} - {{ $subject->description }}</p>
    </div>
</div>

<form method="POST" action="{{ route('program-head.subjects.enroll.store', $subject) }}">
    @csrf

    <label>
        Section
        <select name="block_id" required>
            <option value="">Select section</option>
            @foreach($blocks as $block)
                <option value="{{ $block->id }}">{{ $block->name }}</option>
            @endforeach
        </select>
    </label>

    <div class="table-container">
        <table class="data-table">
            <thead>
            <tr>
                <th>Select</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Student Subject</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($students as $student)
                <tr>
                    <td>
                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}">
                    </td>
                    <td>{{ $student->student_number }}</td>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->subject }}</td>
                    <td>
                        {{ in_array($student->id, $enrolledStudentIds, true) ? 'Already enrolled' : 'Not enrolled' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-message">No active students found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <button class="button primary" type="submit">Confirm Enrollment</button>
</form>

@endsection
