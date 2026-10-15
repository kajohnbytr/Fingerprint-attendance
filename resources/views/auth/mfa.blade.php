<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify sign-in</title>
    @vite('resources/css/program_head/portal.css')
</head>
<body class="login-page">
<form class="login-card" method="POST" action="{{ route('mfa.verify') }}">
    @csrf
    <div class="login-heading">
        <h1>Verify your sign-in</h1>
        <p>Enter the six-digit code sent to your email address.</p>
    </div>
    @if($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif
    <label class="login-field">
        <span>Verification code</span>
        <span class="input-wrap">
            <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
        </span>
    </label>
    <button class="button primary" type="submit">Verify and sign in</button>
</form>
</body>
</html>
