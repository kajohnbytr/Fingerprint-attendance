@extends('program_head.layouts.app')
@section('title', 'Faculty · Announcements & Complaints')
@section('heading', 'Announcements & Complaints')
@section('subheading', 'Subject updates and student concerns for your teaching assignments.')
@section('content')
<div class="page-title"><div><h1>Announcements & Complaints</h1><p>Published announcements and recorded student concerns appear on your dashboard.</p></div></div>
<div class="dashboard-grid faculty-equal-grid">
    <section class="panel" id="announcements">
        <div class="panel-heading"><h2>Announcements</h2></div>
        <details class="faculty-note"><summary>Publish a subject announcement</summary>
            <form class="faculty-form" method="POST" action="{{ route('faculty.announcements.store') }}">
                @csrf
                <label>Subject<select name="subject_id" required><option value="">Select your subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->code }}</option>@endforeach</select></label>
                <label>Title<input name="title" maxlength="150" value="{{ old('title') }}" required></label>
                <label>Announcement<textarea name="body" rows="4" maxlength="5000" required>{{ old('body') }}</textarea></label>
                <button class="button primary" type="submit">Publish announcement</button>
            </form>
        </details>
        @forelse($announcements as $announcement)
            <article class="faculty-message" id="announcement-{{ $announcement->id }}"><strong>{{ $announcement->title }}</strong><small>{{ $announcement->subject_code ?? 'Campus-wide' }} · {{ $announcement->author_name }} · Updated {{ \Carbon\Carbon::parse($announcement->updated_at ?? $announcement->published_at)->diffForHumans() }}</small><p>{{ $announcement->body }}</p></article>
        @empty
            <p class="empty">No published announcements.</p>
        @endforelse
        <div class="pagination">{{ $announcements->links('pagination::simple-bootstrap-5') }}</div>
    </section>
    <section class="panel" id="complaints">
        <div class="panel-heading"><h2>Student complaints</h2></div>
        <details class="faculty-note"><summary>Record a received student complaint</summary>
            <p class="faculty-muted">Log a concern received from a student in one of your subjects.</p>
            <form class="faculty-form" method="POST" action="{{ route('faculty.complaints.store') }}">
                @csrf
                <label>Student<select name="student_id" required><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->full_name }} ({{ $student->student_number }})</option>@endforeach</select></label>
                <label>Enrolled subject<select name="subject_id" required><option value="">Select subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->code }}</option>@endforeach</select></label>
                <label>Title<input name="title" maxlength="150" value="{{ old('title') }}" required></label>
                <label>Complaint<textarea name="body" rows="4" maxlength="5000" required>{{ old('body') }}</textarea></label>
                <button class="button primary" type="submit">Record complaint</button>
            </form>
        </details>
        @forelse($complaints as $complaint)
            <article class="faculty-message" id="complaint-{{ $complaint->id }}"><strong>{{ $complaint->title }}</strong><small>{{ $complaint->first_name }} {{ $complaint->last_name }} · {{ $complaint->student_number }} · {{ $complaint->subject_code }} · {{ \Carbon\Carbon::parse($complaint->created_at)->format('M j, Y') }}</small><p>{{ $complaint->body }}</p>
                <form class="actions" method="POST" action="{{ route('faculty.complaints.update', $complaint->id) }}">
                    @csrf @method('PATCH')
                    <label>Status <select name="status" aria-label="Status for {{ $complaint->title }}">@foreach(['open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved'] as $value => $label)<option value="{{ $value }}" @selected($complaint->status === $value)>{{ $label }}</option>@endforeach</select></label>
                    <button class="button" type="submit">Update</button>
                </form>
            </article>
        @empty
            <p class="empty">No student complaints recorded.</p>
        @endforelse
        <div class="pagination">{{ $complaints->links('pagination::simple-bootstrap-5') }}</div>
    </section>
</div>
@endsection
