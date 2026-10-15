<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Lockdown</title>
    @vite('resources/css/program_head/portal.css')
</head>
<body class="error-page">
    <main class="error-card" role="alert">
        <span class="error-code" aria-hidden="true">!</span>
        <h1>System Lockdown</h1>
        <p>The attendance system has been temporarily locked by the Super Administrator because of a security concern or detected anomaly.</p>
        <p>Please wait for the Super Administrator to restore access.</p>
        <div class="error-actions">
            <a class="button primary" href="{{ route('login') }}">Return to sign in</a>
        </div>
    </main>
</body>
</html>
