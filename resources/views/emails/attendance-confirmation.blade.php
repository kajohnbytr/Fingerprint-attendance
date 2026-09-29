<!DOCTYPE html>
<html lang="en">
<body>
    <h1>Attendance confirmed</h1>
    <p>Hello {{ $studentName }},</p>
    <p>Your fingerprint attendance was successfully recorded.</p>
    <p>
        <strong>Subject:</strong> {{ $subjectName }}<br>
        <strong>Subject code:</strong> {{ $subjectCode }}<br>
        <strong>Time:</strong> {{ $attendedAt->format('F j, Y g:i A') }}
    </p>
</body>
</html>
