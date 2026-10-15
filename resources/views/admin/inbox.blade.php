@extends('program_head.layouts.app')
@section('title', auth()->user()->role === 'program_head' ? 'Program Head · Inbox' : 'System Admin · Announcements & Complaints')
@section('heading', auth()->user()->role === 'program_head' ? 'Program Head inbox' : 'Announcements & Complaints')
@section('content')
<div class="page-title"><div><h1>{{ auth()->user()->role === 'program_head' ? 'Program Head inbox' : 'Campus communications' }}</h1><p>{{ auth()->user()->role === 'program_head' ? 'Recent announcements and student concerns across the program.' : 'Publish announcements and review saved student complaints.' }}</p></div></div>
<div class="dashboard-grid faculty-equal-grid">
<section class="panel" id="announcements"><div class="panel-heading"><h2>Announcements</h2></div>
    @if(auth()->user()->role === 'admin')
        <details class="faculty-note"><summary>Publish announcement</summary><form class="faculty-form" method="POST" action="{{ route('admin.announcements.store') }}">@csrf
            <label>Audience<select name="subject_id"><option value="">Campus-wide</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->code }}</option>@endforeach</select></label>
            <label>Title<input name="title" value="{{ old('title') }}" maxlength="150" required></label>
            <label>Announcement<textarea name="body" rows="5" maxlength="5000" required>{{ old('body') }}</textarea></label>
            <button class="button primary" type="submit">Publish announcement</button>
        </form></details>
    @endif
    @forelse($announcements as $announcement)<article class="faculty-message" id="announcement-{{ $announcement->id }}"><a href="{{ auth()->user()->role === 'admin' ? route('admin.announcements.show', $announcement->id) : '#announcement-'.$announcement->id }}"><strong>{{ $announcement->title }}</strong></a><small>{{ $announcement->subject_code ?? 'Campus-wide' }} · {{ $announcement->author_name }} · Updated {{ \Carbon\Carbon::parse($announcement->updated_at ?? $announcement->published_at)->diffForHumans() }}</small><p>{{ \Illuminate\Support\Str::limit($announcement->body, 200) }}</p></article>@empty<p class="empty">No announcements saved.</p>@endforelse
    <div class="pagination">{{ $announcements->links('pagination::simple-bootstrap-5') }}</div>
</section>
<section class="panel" id="complaints"><div class="panel-heading"><h2>Student complaints</h2></div>
    <form class="filters faculty-note" method="GET"><label>Status <select name="complaint_status"><option value="">All statuses</option>@foreach(['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'] as $status => $label)<option value="{{ $status }}" @selected(request('complaint_status') === $status)>{{ $label }}</option>@endforeach</select></label><button class="button" type="submit">Filter</button></form>
    @forelse($complaints as $complaint)<article class="faculty-message" id="complaint-{{ $complaint->id }}"><a href="{{ auth()->user()->role === 'admin' ? route('admin.complaints.show', $complaint->id) : '#complaint-'.$complaint->id }}"><strong>{{ $complaint->title }}</strong></a><small>{{ $complaint->first_name }} {{ $complaint->last_name }} · {{ $complaint->subject_code }} · {{ ['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'][$complaint->status] ?? $complaint->status }}</small><p>{{ \Illuminate\Support\Str::limit($complaint->body, 200) }}</p></article>@empty<p class="empty">No complaints match this status.</p>@endforelse
    <div class="pagination">{{ $complaints->links('pagination::simple-bootstrap-5') }}</div>
</section>
</div>
@endsection
