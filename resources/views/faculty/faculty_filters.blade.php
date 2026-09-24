<form method="GET" class="filters faculty-filters">
    @if($dates ?? true)
        <label>From<input type="date" name="from" value="{{ $filters['from'] }}" max="{{ now()->toDateString() }}" required></label>
        <label>To<input type="date" name="to" value="{{ $filters['to'] }}" max="{{ now()->toDateString() }}" required></label>
    @endif
    @if($showStudents ?? true)
        <label>Student<select name="student_id"><option value="">All my students</option>
            @foreach($students as $option)
                <option value="{{ $option->id }}" @selected(($filters['student_id'] ?? '') == $option->id)>{{ $option->full_name }} ({{ $option->student_number }})</option>
            @endforeach
        </select></label>
    @endif
    <label>Section<select name="block_id"><option value="">All my sections</option>
        @foreach($blocks as $block)
            <option value="{{ $block->id }}" @selected(($filters['block_id'] ?? '') == $block->id)>{{ $block->name }}</option>
        @endforeach
    </select></label>
    <label>Subject<select name="subject_id"><option value="">All my subjects</option>
        @foreach($subjects as $subject)
            <option value="{{ $subject->id }}" @selected(($filters['subject_id'] ?? '') == $subject->id)>{{ $subject->code }} — {{ $subject->description }}</option>
        @endforeach
    </select></label>
    @if($showStatus ?? true)
        <label>Status<select name="status"><option value="">All statuses</option>
            @foreach(['present', 'absent', 'late', 'pending'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select></label>
    @endif
    @if($report ?? false)
        <label>Report<select name="type">
            <option value="attendance" @selected(($filters['type'] ?? 'attendance') === 'attendance')>Attendance</option>
            <option value="enrollment" @selected(($filters['type'] ?? '') === 'enrollment')>Enrollment</option>
        </select></label>
    @endif
    <button class="button primary" type="submit">Apply filters</button>
    <a class="button" href="{{ url()->current() }}">Reset</a>
</form>
