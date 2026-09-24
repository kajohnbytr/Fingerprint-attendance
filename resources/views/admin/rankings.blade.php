<div class="dashboard-grid faculty-equal-grid">
    @foreach(['absent' => 'Most absences', 'late' => 'Most late arrivals'] as $status => $label)
        <section class="panel"><div class="panel-heading"><div><h2>{{ $label }}</h2><p>Top ten students, {{ $filters['from'] }} through {{ $filters['to'] }}.</p></div></div>
        @forelse($rankings->where($status, '>', 0)->sortByDesc($status)->take(10) as $row)
            <div class="activity"><div><strong>{{ $row['student']->full_name }}</strong><small>{{ $row['student']->student_number }}</small></div><span class="badge attendance-{{ $status }}">{{ $row[$status] }} {{ $status }}</span>
            @if(!$row['student']->trashed())<a href="{{ route('admin.students.attendance', ['student' => $row['student'], 'from' => $filters['from'], 'to' => $filters['to'], 'status' => $status]) }}">History</a>@else<span>Archived</span>@endif</div>
        @empty<p class="empty">No {{ $status === 'absent' ? 'absences' : 'late arrivals' }} in this period.</p>@endforelse
        </section>
    @endforeach
</div>
