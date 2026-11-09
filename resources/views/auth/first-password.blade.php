<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your password · {{ $universityName }} MIS Helpdesk</title>
    <link rel="icon" type="image/png" href="{{ asset('images/city-of-ilagan-college-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="login-page">
    <main class="login-panel">
        <div class="d-flex align-items-center gap-3 mb-4">
            <img src="{{ asset('images/city-of-ilagan-college-logo.png') }}" alt="City of Ilagan College logo" class="institution-logo institution-logo-lg">
            <div>
                <div class="login-system">{{ $universityName }} MIS Helpdesk</div>
                <div class="text-muted small">Set your account password</div>
            </div>
        </div>
        <div class="login-rule"></div>
        <h1>Choose a new password</h1>
        <p class="text-muted">This temporary password must be replaced before you can use the system.</p>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.first-change.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="current_password">Temporary password</label>
                <input id="current_password" name="current_password" type="password" class="form-control form-control-lg" autocomplete="current-password" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="new-password" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-lg" autocomplete="new-password" required>
                <div class="form-hint mt-2">Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.</div>
            </div>
            <button class="btn btn-primary btn-lg w-100">Set password</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button type="submit" class="btn btn-link">Sign out</button></form>
    </main>
</body>
</html>
