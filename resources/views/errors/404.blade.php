<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found</title>
    @vite(['resources/css/program_head/portal.css', 'resources/js/app.js'])
</head>
<body class="error-page">
<main class="error-card">
    <span class="error-code">404</span>
    <p class="eyebrow">Lost, but not stuck</p>
    <h1>That page left the campus.</h1>
    <p>The address may be outdated, or you may not have access to that destination.</p>
    <div class="error-actions">
        <a class="button primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Return to {{ auth()->check() ? 'dashboard' : 'sign in' }}</a>
        <button class="button history-back" type="button">Go back</button>
    </div>
</main>
</body>
</html>
