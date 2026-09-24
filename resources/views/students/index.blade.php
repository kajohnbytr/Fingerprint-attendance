@extends('program_head.layouts.app')

@section('title', 'Enrolled Students')
@section('heading', 'Enrolled Students')
@section('subheading', 'Search and manage enrollment records.')

@section('content')

<div class="page-title">
    <div>
        <h1>Enrolled Students</h1>
        <p>Securely manage student information.</p>
    </div>

    @if(auth()->user()->role === 'admin')
        <a
            class="button primary"
            href="{{ route('students.create') }}"
        >
            + Add Student
        </a>
    @endif
</div>

<form class="filters" method="GET">
    <input
        name="search"
        value="{{ request('search') }}"
        placeholder="Search ID, name or email"
    >

    <select name="subject">
        <option value="">All subjects</option>

        @foreach($subjects as $subject)
            <option
                value="{{ $subject }}"
                @selected(request('subject') === $subject)
            >
                {{ $subject }}
            </option>
        @endforeach
    </select>

    <select name="year_level">
        <option value="">All years</option>

        @for($year = 1; $year <= 6; $year++)
            <option
                value="{{ $year }}"
                @selected(
                    (string) request('year_level')
                    === (string) $year
                )
            >
                Year {{ $year }}
            </option>
        @endfor
    </select>

    <select name="block_id">
        <option value="">All blocks</option>

        @foreach($blocks as $block)
            <option
                value="{{ $block->id }}"
                @selected(
                    (string) request('block_id')
                    === (string) $block->id
                )
            >
                {{ $block->name }}
            </option>
        @endforeach
    </select>

    <select name="status">
        <option value="">All statuses</option>

        @foreach(['active', 'inactive', 'graduated'] as $status)
            <option
                value="{{ $status }}"
                @selected(request('status') === $status)
            >
                {{ ucfirst($status) }}
            </option>
        @endforeach
    </select>

    <button class="button" type="submit">
        Filter
    </button>

    <a class="button" href="{{ route('students.index') }}">
        Reset
    </a>
</form>

<div class="table-container">
    <table>
        <thead>
        <tr>
            <th>Student ID</th>
            <th>Full name</th>
            <th>Subject</th>
            <th>Year</th>
            <th>Block</th>
            <th>Assigned Subjects</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        @forelse($students as $student)
            <tr>
                <td>{{ $student->student_number }}</td>

                <td>
                    <strong>{{ $student->full_name }}</strong>
                    <small>{{ $student->email }}</small>
                </td>

                <td>{{ $student->subject }}</td>
                <td>{{ $student->year_level }}</td>

                <td>
                    {{ $student->block?->name ?? 'Unassigned' }}
                </td>

                <td>
                    @forelse($student->subjects as $subject)
                        <span class="status enrolled">
                            {{ $subject->code }}
                        </span>
                    @empty
                        <span class="status not-enrolled">
                            None assigned
                        </span>
                    @endforelse
                </td>

                <td>
                    <span class="badge {{ $student->status }}">
                        {{ ucfirst($student->status) }}
                    </span>
                </td>

                <td class="actions">
                    <a href="{{ route('students.show', $student) }}">
                        View
                    </a>

                    @if(auth()->user()->role === 'program_head')
                        <a href="{{ route('program-head.students.enroll', $student) }}">
                            Enroll To
                        </a>
                    @endif

                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('students.edit', $student) }}">
                            Edit
                        </a>

                        <form
                            method="POST"
                            action="{{ route('students.destroy', $student) }}"
                            onsubmit="return confirm(
                                'Archive this student record?'
                            )"
                        >
                            @csrf
                            @method('DELETE')

                            <button class="danger-link" type="submit">
                                Delete
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="empty">
                    No students matched your filters.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">
    {{ $students->links() }}
</div>

@endsection
