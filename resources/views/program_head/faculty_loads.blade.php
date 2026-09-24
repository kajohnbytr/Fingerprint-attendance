@extends('program_head.layouts.app')

@section('title', 'Faculty Teaching Loads')

@section('content')

<div class="page-title">
    <div>
        <h1>Faculty Teaching Loads</h1>

        <p>
            Track assigned subjects, sections and schedules.
        </p>
    </div>
</div>

@forelse($facultyMembers as $faculty)
    <section class="panel faculty-card">
        <div class="faculty-header">
            <div class="faculty-avatar">
                {{ strtoupper(substr(
                    $faculty->name,
                    0,
                    1
                )) }}
            </div>

            <div>
                <h2>{{ $faculty->name }}</h2>
                <p>{{ $faculty->email }}</p>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Section</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Room</th>
                </tr>
                </thead>

                <tbody>
                @forelse(
                    $faculty->assignedSubjects as $subject
                )
                    <tr>
                        <td>
                            <strong>
                                {{ $subject->code }}
                            </strong>
                        </td>

                        <td>
                            {{ $subject->description }}
                        </td>

                        <td>
                            {{
                                $subject->enrollments
                                    ->pluck('block.name')
                                    ->filter()
                                    ->unique()
                                    ->join(', ')
                                ?: 'No section enrolled'
                            }}
                        </td>

                        <td>{{ $subject->day }}</td>

                        <td>
                            {{
                                \Carbon\Carbon::parse($subject->start_time)->format('g:i A')
                            }}
                            –
                            {{
                                \Carbon\Carbon::parse($subject->end_time)->format('g:i A')
                            }}
                        </td>

                        <td>{{ $subject->room }}</td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="empty-message"
                        >
                            No subjects assigned.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@empty
    <section class="panel faculty-card">
        <p class="empty-message">
            No faculty records found.
        </p>
    </section>
@endforelse

@endsection