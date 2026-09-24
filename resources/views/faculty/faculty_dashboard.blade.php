@extends('program_head.layouts.app')
@section('title', 'Faculty Dashboard')
@section('heading', 'Faculty dashboard')
@section('subheading', 'Your students, attendance trends, and items needing attention.')
@section('content')
<div class="page-title"><div><h1>My teaching overview</h1><p>{{ $filters['from'] }} through {{ $filters['to'] }} · Saved enrollments and fingerprint scans</p></div><a class="button" href="{{ route('faculty.reports', $filters) }}">View reports</a></div>
@include('faculty.faculty_filters', ['showStudents' => false, 'showStatus' => false])
@include('faculty.faculty_counts')

<div class="dashboard-grid faculty-equal-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Recent announcements</h2><p>Campus updates and your assigned subjects.</p></div><a href="{{ route('faculty.inbox') }}#announcements">View all</a></div>
        @forelse($announcements as $announcement)
            <article class="faculty-message"><strong>{{ $announcement->title }}</strong><small>{{ $announcement->subject_code ?? 'Campus-wide' }} · {{ $announcement->author_name }} · {{ \Carbon\Carbon::parse($announcement->published_at)->format('M j, Y') }}</small><p>{{ \Illuminate\Support\Str::limit($announcement->body, 220) }}</p></article>
        @empty
            <p class="empty">No published announcements yet.</p>
        @endforelse
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Student complaints</h2><p>Open and in-review concerns, newest first within status.</p></div><a href="{{ route('faculty.inbox') }}#complaints">Review all</a></div>
        @forelse($complaints as $complaint)
            <article class="faculty-message"><strong>{{ $complaint->title }}</strong><small>{{ $complaint->first_name }} {{ $complaint->last_name }} · {{ $complaint->subject_code }} · {{ str_replace('_', ' ', ucfirst($complaint->status)) }}</small><p>{{ \Illuminate\Support\Str::limit($complaint->body, 220) }}</p></article>
        @empty
            <p class="empty">No unresolved student complaints.</p>
        @endforelse
    </section>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Daily attendance trend</h2><p>Inferred student-class sessions. Select a day to inspect attendance.</p></div></div>
        <div class="faculty-legend"><span class="attendance-present">Present</span><span class="attendance-absent">Absent</span><span class="attendance-late">Late</span></div>
        @php($maximum = max(1, $trend->map(fn ($day) => $day['present'] + $day['absent'] + $day['late'])->max()))
        <div class="faculty-chart" role="img" aria-label="Daily attendance trend. Exact counts are available in the table below.">
            @foreach($trend as $day)
                <a class="faculty-chart-column" href="{{ route('faculty.attendance', array_merge($filters, ['from' => $day['date'], 'to' => $day['date']])) }}" aria-label="{{ $day['date'] }}: {{ $day['present'] }} present, {{ $day['absent'] }} absent, {{ $day['late'] }} late">
                    <span class="faculty-chart-total">{{ $day['present'] + $day['absent'] + $day['late'] }}</span>
                    <span class="faculty-chart-stack">
                        @foreach(['late', 'absent', 'present'] as $status)
                            <span class="faculty-bar-{{ $status }}" style="height: {{ $day[$status] / $maximum * 140 }}px"></span>
                        @endforeach
                    </span>
                    <small>{{ \Carbon\Carbon::parse($day['date'])->format('m/d') }}</small>
                </a>
            @endforeach
        </div>
        @if(array_sum($counts) === 0)<p class="empty">No class attendance sessions in this period.</p>@endif
        <details class="faculty-note"><summary>Show daily counts</summary><div class="table-container"><table>
            <thead><tr><th>Date</th><th>Present</th><th>Absent</th><th>Late</th><th>Pending</th></tr></thead>
            <tbody>@foreach($trend as $day)<tr><td>{{ $day['date'] }}</td><td>{{ $day['present'] }}</td><td>{{ $day['absent'] }}</td><td>{{ $day['late'] }}</td><td>{{ $day['pending'] }}</td></tr>@endforeach</tbody>
        </table></div></details>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Attendance breakdown</h2><p>Selected period, excluding pending sessions.</p></div></div>
        @php($completed = max(1, $counts['present'] + $counts['absent'] + $counts['late']))
        @foreach(['present', 'absent', 'late'] as $status)
            <div class="faculty-breakdown"><strong>{{ ucfirst($status) }} · {{ $counts[$status] }}</strong><span>{{ round($counts[$status] / $completed * 100, 1) }}%</span><div class="faculty-meter"><div class="faculty-bar-{{ $status }}" style="width: {{ $counts[$status] / $completed * 100 }}%"></div></div></div>
        @endforeach
    </section>
</div>
<div class="dashboard-grid faculty-equal-grid">
    @foreach(['absent' => 'Most absences', 'late' => 'Most late arrivals'] as $status => $title)
        <section class="panel"><div class="panel-heading"><div><h2>{{ $title }}</h2><p>Top five students in the selected period.</p></div></div>
            @forelse($rankings[$status] as $ranking)
                <div class="activity"><div><strong>{{ $ranking['student']->full_name }}</strong><small>{{ $ranking['student']->student_number }}</small></div><span class="badge attendance-{{ $status }}">{{ $ranking['count'] }} {{ $status }}</span>
                    @if(!$ranking['student']->trashed())<a href="{{ route('faculty.students.attendance', ['student' => $ranking['student'], 'from' => $filters['from'], 'to' => $filters['to'], 'status' => $status]) }}">History</a>@endif
                </div>
            @empty
                <p class="empty">No {{ $status === 'absent' ? 'absences' : 'late arrivals' }} in this period.</p>
            @endforelse
        </section>
    @endforeach
</div>
@include('faculty.faculty_attendance_note')
@endsection
