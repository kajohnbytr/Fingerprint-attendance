@extends('program_head.layouts.app')

@section('title', 'Schedule')
@section('heading', 'Class Schedule')
@section(
    'subheading',
    'Manage subjects, instructors, rooms and times.'
)

@section('content')

<div class="page-title">
    <div>
        <h1>Schedule</h1>
        <p>Class schedules organized by block.</p>
    </div>

    <form method="GET">
        <select
            name="block_id"
            onchange="this.form.submit()"
        >
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
    </form>
</div>

@if(auth()->user()->role === 'admin')
    <form
        class="filters schedule-form"
        method="POST"
        action="{{ route('schedules.store') }}"
    >
        @csrf

        <select name="block_id" required>
            <option value="">Select block</option>

            @foreach($blocks as $block)
                <option value="{{ $block->id }}">
                    {{ $block->name }}
                </option>
            @endforeach
        </select>

        <input
            name="subject_code"
            placeholder="Subject code"
            required
        >

        <input
            name="subject_name"
            placeholder="Subject name"
            required
        >

        <select name="day" required>
            @foreach([
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday'
            ] as $day)
                <option value="{{ $day }}">
                    {{ $day }}
                </option>
            @endforeach
        </select>

        <input
            type="time"
            name="start_time"
            required
        >

        <input
            type="time"
            name="end_time"
            required
        >

        <input
            name="room"
            placeholder="Room"
            required
        >

        <input
            name="instructor"
            placeholder="Instructor"
            required
        >

        <button class="button primary" type="submit">
            Add Schedule
        </button>
    </form>
@endif

<div class="table-container">
    <table>
        <thead>
        <tr>
            <th>Block</th>
            <th>Subject</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Instructor</th>

            @if(auth()->user()->role === 'admin')
                <th>Action</th>
            @endif
        </tr>
        </thead>

        <tbody>
        @forelse($schedules as $schedule)
            <tr>
                <td>{{ $schedule->block->name }}</td>

                <td>
                    <strong>{{ $schedule->subject_code }}</strong>
                    <small>{{ $schedule->subject_name }}</small>
                </td>

                <td>{{ $schedule->day }}</td>

                <td>
                    {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }}
                    –
                    {{ \Carbon\Carbon::parse($schedule->end_time)->format('g:i A') }}
                </td>

                <td>{{ $schedule->room }}</td>
                <td>{{ $schedule->instructor }}</td>

                @if(auth()->user()->role === 'admin')
                    <td>
                        <form
                            method="POST"
                            action="{{ route(
                                'schedules.destroy',
                                $schedule
                            ) }}"
                            onsubmit="return confirm(
                                'Delete this schedule?'
                            )"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                class="danger-link"
                                type="submit"
                            >
                                Delete
                            </button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="7" class="empty">
                    No schedules available.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $schedules->links() }}

@endsection
