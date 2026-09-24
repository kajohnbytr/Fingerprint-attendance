@extends('program_head.layouts.app')
@section('title', 'System Admin Dashboard')
@section('heading', 'System Admin dashboard')
@section('subheading', 'Campus attendance, student concerns, and recent updates.')
@section('content')
<div class="page-title"><div><h1>Campus overview</h1><p>Today · {{ now()->toDateString() }} · {{ config('app.timezone') }}</p></div><a class="button" href="{{ route('admin.attendance', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}">Today’s attendance</a></div>
<div class="statistics"><article><small>Total students</small><strong>{{ number_format($studentCount) }}</strong><span>Existing, non-archived records</span></article>
@foreach(['present', 'absent', 'late'] as $status)<article><small>{{ ucfirst($status) }} today</small><strong>{{ number_format($counts[$status]) }}</strong><span>Inferred student-class attendances</span></article>@endforeach</div>
<p class="faculty-muted">{{ $counts['pending'] }} ongoing student-class sessions are pending. A student attending several classes can count more than once.</p>
<div class="dashboard-grid faculty-equal-grid">
    <section class="panel"><div class="panel-heading"><h2>Recent announcements</h2><a href="{{ route('admin.inbox') }}#announcements">View all</a></div>
        @forelse($announcements as $announcement)<article class="faculty-message"><a href="{{ route('admin.announcements.show', $announcement->id) }}"><strong>{{ $announcement->title }}</strong></a><small>{{ $announcement->subject_code ?? 'Campus-wide' }} · {{ $announcement->author_name }} · {{ \Carbon\Carbon::parse($announcement->published_at)->format('M j, Y') }}</small><p>{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</p></article>@empty<p class="empty">No announcements published yet.</p>@endforelse
    </section>
    <section class="panel"><div class="panel-heading"><h2>Student complaints</h2><a href="{{ route('admin.inbox') }}#complaints">Review all</a></div>
        @forelse($complaints as $complaint)<article class="faculty-message"><a href="{{ route('admin.complaints.show', $complaint->id) }}"><strong>{{ $complaint->title }}</strong></a><small>{{ $complaint->first_name }} {{ $complaint->last_name }} · {{ $complaint->subject_code }} · {{ ['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'][$complaint->status] ?? $complaint->status }}</small><p>{{ \Illuminate\Support\Str::limit($complaint->body, 180) }}</p></article>@empty<p class="empty">No unresolved complaints.</p>@endforelse
    </section>
</div>
<section class="panel faculty-note"><div class="panel-heading"><div><h2>Attendance trends</h2><p>Filters below apply to trends and rankings. Today’s totals above always cover the campus.</p></div></div>
@include('admin.filters', ['showStudents' => false, 'showStatus' => false])
<div class="faculty-legend"><span class="attendance-present">Present</span><span class="attendance-absent">Absent</span><span class="attendance-late">Late</span></div>
@php($maximum = max(1, $trend->map(fn ($day) => $day['present'] + $day['absent'] + $day['late'])->max()))
<div class="faculty-chart" aria-label="Daily attendance chart; exact counts are in the table below.">
    @foreach($trend as $day)<a class="faculty-chart-column" href="{{ route('admin.attendance', array_merge($filters, ['from' => $day['date'], 'to' => $day['date']])) }}" aria-label="{{ $day['date'] }}: {{ $day['present'] }} present, {{ $day['absent'] }} absent, {{ $day['late'] }} late"><span class="faculty-chart-total">{{ $day['present'] + $day['absent'] + $day['late'] }}</span><span class="faculty-chart-stack">@foreach(['late', 'absent', 'present'] as $status)<span class="faculty-bar-{{ $status }}" style="height: {{ $day[$status] / $maximum * 140 }}px"></span>@endforeach</span><small>{{ \Carbon\Carbon::parse($day['date'])->format('m/d') }}</small></a>@endforeach
</div>
<details><summary>Daily counts</summary><div class="table-container"><table><thead><tr><th>Date</th><th>Present</th><th>Absent</th><th>Late</th><th>Pending</th></tr></thead><tbody>@foreach($trend as $day)<tr><td>{{ $day['date'] }}</td><td>{{ $day['present'] }}</td><td>{{ $day['absent'] }}</td><td>{{ $day['late'] }}</td><td>{{ $day['pending'] }}</td></tr>@endforeach</tbody></table></div></details>
</section>
@include('admin.rankings')
@include('faculty.faculty_attendance_note')
@endsection
