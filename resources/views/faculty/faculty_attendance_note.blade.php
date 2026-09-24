<details class="panel faculty-note">
    <summary>Attendance estimates · {{ $preferences['late_minutes'] }} minute grace period · {{ config('app.timezone') }}</summary>
    <p>Counts represent student-class sessions, so a student can appear in multiple classes. The first fingerprint scan between class start (inclusive) and end (exclusive) determines present or late. A scan after {{ $preferences['late_minutes'] }} minutes is late. A completed class without a matching scan is inferred absent; an ongoing class is pending.</p>
    <p>Estimates use current Program Head enrollments and weekly subject schedules, beginning after enrollment. Scans do not identify a subject or classroom. Early scans, holidays, cancellations, overlapping classes, and past schedule changes cannot be verified from these records. Review inferred absences before taking action.</p>
</details>
