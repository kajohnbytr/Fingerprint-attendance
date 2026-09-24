@extends('program_head.layouts.app')
@section('title', 'System Admin · '.ucfirst($kind))
@section('heading', ucfirst($kind).' details')
@section('content')
<div class="page-title"><div><h1>{{ $record->title }}</h1><p>{{ $record->subject_code ?? 'Campus-wide' }}</p></div><a href="{{ route('admin.inbox') }}">Back to announcements & complaints</a></div>
<section class="panel faculty-message">
    @if($kind === 'announcement')<small>Published by {{ $record->author_name }} · {{ \Carbon\Carbon::parse($record->published_at)->format('M j, Y g:i A') }}</small>
    @else<small>{{ $record->first_name }} {{ $record->last_name }} · {{ $record->student_number }} · Received {{ \Carbon\Carbon::parse($record->created_at)->format('M j, Y g:i A') }}</small>@endif
    <p>{{ $record->body }}</p>
    @if($kind === 'complaint')
        <form class="actions" method="POST" action="{{ route('admin.complaints.update', $record->id) }}">@csrf @method('PATCH')<label>Complaint status <select name="status">@foreach(['open' => 'Pending', 'in_review' => 'In progress', 'resolved' => 'Resolved'] as $status => $label)<option value="{{ $status }}" @selected($record->status === $status)>{{ $label }}</option>@endforeach</select></label><button class="button primary" type="submit">Save status</button></form>
    @endif
</section>
@endsection
