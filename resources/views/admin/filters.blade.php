<form method="GET" class="filters faculty-filters">
    @if($dates ?? true)
        <label>From<input type="date" name="from" value="{{ $filters['from'] }}" max="{{ now()->toDateString() }}" required></label>
        <label>To<input type="date" name="to" value="{{ $filters['to'] }}" max="{{ now()->toDateString() }}" required></label>
    @endif
    @if($search ?? false)<label>Search students<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or student number" maxlength="100"></label>@endif
    @if($showStudents ?? true)
        <label>Student<select name="student_id"><option value="">All students</option>@foreach($students as $option)<option value="{{ $option->id }}" @selected(($filters['student_id'] ?? '') == $option->id)>{{ $option->full_name }} ({{ $option->student_number }})</option>@endforeach</select></label>
    @endif
    <label>Section<select name="block_id"><option value="">All sections</option>@foreach($blocks as $block)<option value="{{ $block->id }}" @selected(($filters['block_id'] ?? '') == $block->id)>{{ $block->name }}</option>@endforeach</select></label>
    <label>Subject<select name="subject_id"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(($filters['subject_id'] ?? '') == $subject->id)>{{ $subject->code }} — {{ $subject->description }}</option>@endforeach</select></label>
    @if($showFaculty ?? false)<label>Faculty<select name="faculty_id"><option value="">All faculty</option>@foreach($faculty as $member)<option value="{{ $member->id }}" @selected(($filters['faculty_id'] ?? '') == $member->id)>{{ $member->name }}</option>@endforeach</select></label>@endif
    @if($showStatus ?? true)
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['present', 'absent', 'late', 'pending'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    @endif
    @if($report ?? false)<label>Report<select name="type"><option value="attendance" @selected(($filters['type'] ?? 'attendance') === 'attendance')>Attendance</option><option value="enrollment" @selected(($filters['type'] ?? '') === 'enrollment')>Enrollment</option></select></label>@endif
    <button type="submit" class="button primary">Apply filters</button><a class="button" href="{{ url()->current() }}">Reset</a>
</form>
