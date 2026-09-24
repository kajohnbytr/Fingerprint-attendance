<div class="statistics faculty-statistics">
    @if(isset($studentCount))
        <article><small>Total students</small><strong>{{ number_format($studentCount) }}</strong><span>Distinct, non-archived students in my subjects</span></article>
    @endif
    @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'] as $key => $label)
        <article><small>{{ $label }}</small><strong>{{ number_format($counts[$key]) }}</strong><span>Inferred class attendances in selected period</span></article>
    @endforeach
</div>
<p class="faculty-muted">{{ number_format($counts['pending']) }} pending student-class sessions are excluded from present, absent, and late counts.</p>
