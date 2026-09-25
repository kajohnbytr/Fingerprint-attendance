<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Sign in</title>

    @vite('resources/css/program_head/portal.css')
</head>

<body class="login-page">

<form
    class="login-card"
    method="POST"
    action="{{ route('login.store') }}"
>
    @csrf

    <div class="login-heading">
        <span class="login-lock" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="presentation">
                <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            </svg>
        </span>
        <h1>Portal Login</h1>
        <p>Enter your credentials to continue</p>
    </div>

    @if($errors->any())
        <div class="alert error">
            {{ $errors->first() }}
        </div>
    @endif

    <label class="login-field">
        <span>Email Address</span>
        <span class="input-wrap">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                <path d="m3 7 9 6 9-6"></path>
            </svg>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="ADMIN ID"
                autocomplete="username"
                required
                autofocus
            >
        </span>
    </label>

    <label class="login-field">
        <span>Password</span>
        <span class="input-wrap">
            <button
                class="password-toggle password-toggle-left"
                type="button"
                aria-label="Show password"
                aria-controls="login-password"
                aria-pressed="false"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"></path>
                    <circle cx="12" cy="12" r="2.5"></circle>
                </svg>
                <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m3 3 18 18"></path>
                    <path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.1 3.7"></path>
                    <path d="M6.5 6.8C3.7 8.5 2.5 12 2.5 12s3.5 6 9.5 6c1 0 1.9-.2 2.7-.5"></path>
                </svg>
            </button>
            <input
                id="login-password"
                type="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >
        </span>
    </label>

    <div class="login-options">
        <label class="checkbox-label">
            <input
                type="checkbox"
                name="remember"
                value="1"
            >
            <span>Remember me</span>
        </label>
        <button class="forgot-link" type="button">Forgot password?</button>
    </div>

    <button class="button primary" type="submit">
        Sign In
    </button>

    @env('local')
    <small>
        Demo: admin@northbridge.test / ChangeMe123!
    </small>
    @endenv

    <div class="login-footer">
        Authorized access only. Your role controls available features.
    </div>
</form>

<script>
    const passwordInput = document.getElementById('login-password');
    const passwordToggle = document.querySelector('.password-toggle');

    passwordToggle.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'text';

        passwordInput.type = isVisible ? 'password' : 'text';
        passwordToggle.classList.toggle('is-visible', !isVisible);
        passwordToggle.setAttribute('aria-pressed', String(!isVisible));
        passwordToggle.setAttribute(
            'aria-label',
            isVisible ? 'Show password' : 'Hide password'
        );
    });
</script>

</body>
</html>
