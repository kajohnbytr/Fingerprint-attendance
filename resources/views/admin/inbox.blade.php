@extends('program_head.layouts.app')
@section('title', 'System Admin · Announcements & Complaints')
@section('heading', 'Announcements & Complaints')
@section('content')
<div class="page-title"><div><h1>Campus communications</h1><p>Publish announcements and review saved student complaints.</p></div></div>
<div class="dashboard-grid faculty-equal-grid">
<section class="panel" id="announcements"><div class="panel-heading"><h2>Announcements</h2></div>
    <details class="faculty-note"><summary>Publish announcement</summary><form class="faculty-form" method="POST" action="{{ route('admin.announcements.store') }}">@csrf
        <label>Audience<select name="subject_id"><option value="">Campus-wide</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->code }}</option>@endforeach</select></label>
        <label>Title<input name="title" value="{{ old('title') }}" maxlength="150" required></label>
        <label>Announcement<textarea name="body" rows="5" maxlength="5000" required>{{ old('body') }}</textarea></label>
        <button class="button primary" type="submit">Publish announcement</button>
    </form></details>
    @forelse($announcements as $announcement)<article class="faculty-message"><a href="{{ route('admin.announcements.show', $announcement->id) }}"><strong>{{ $announcement->title }}</strong></a><small>{{ $announcement->subject_code ?? 'Campus-wide' }} · {{ $announcement->author_name }} · {{ \Carbon\Carbon::parse($announcement->published_at)->format('M j, Y g:i A') }}</small><p>{{ \Illuminate\Support\Str::limit($announcement->body, 200) }}</p></article>@empty<p class="empty">No announcements saved.</p>@endforelse
    <div class="pagination">{{ $announcements->links('pagination::simple-bootstrap-5') }}</div>
</section>
<section class="panel" id="complaints"><div class="panel-heading"><h2>Student complaints</h2></div>
    <form class="filters faculty-note" method="GET"><label>Status <select name="complaint_status"><option value="">All statuses</option>@foreach(['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'] as $status => $label)<option value="{{ $status }}" @selected(request('complaint_status') === $status)>{{ $label }}</option>@endforeach</select></label><button class="button" type="submit">Filter</button></form>
    @forelse($complaints as $complaint)<article class="faculty-message"><a href="{{ route('admin.complaints.show', $complaint->id) }}"><strong>{{ $complaint->title }}</strong></a><small>{{ $complaint->first_name }} {{ $complaint->last_name }} · {{ $complaint->subject_code }} · {{ ['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'][$complaint->status] ?? $complaint->status }}</small><p>{{ \Illuminate\Support\Str::limit($complaint->body, 200) }}</p></article>@empty<p class="empty">No complaints match this status.</p>@endforelse
    <div class="pagination">{{ $complaints->links('pagination::simple-bootstrap-5') }}</div>
</section>
</div>
@endsection
