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

    <div class="login-brand">
        <span class="brand-logo">N</span>
        <h1>Northbridge</h1>
    </div>

    <p>Sign in to the program head portal.</p>

    @if($errors->any())
        <div class="alert error">
            {{ $errors->first() }}
        </div>
    @endif

    <label>
        Email

        <input
            type="email"
            name="email"
            value="{{ old('email') }}"
            autocomplete="username"
            required
            autofocus
        >
    </label>

    <label>
        Password

        <input
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >
    </label>

    <label class="checkbox-label">
        <input
            type="checkbox"
            name="remember"
            value="1"
        >

        Remember me
    </label>

    <button class="button primary" type="submit">
        Sign in
    </button>

    @env('local')
    <small>
        Demo: admin@northbridge.test / ChangeMe123!
    </small>
    @endenv
</form>

</body>
</html>
