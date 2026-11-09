<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:login'],
            'password' => ['required', 'string'],
        ]);
        $identifier = trim((string) ($data['login'] ?? $data['email']));
        $errorKey = $request->filled('email') && ! $request->filled('login') ? 'email' : 'login';
        $key = strtolower($identifier).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([$errorKey => 'Too many sign-in attempts. Please try again in a minute.']);
        }
        $identifierColumn = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'employee_number';
        if (! Auth::attempt([$identifierColumn => $identifier, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            AuditLog::create(['user_id' => null, 'action' => 'login.failed', 'metadata' => ['login_identifier' => $identifier], 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now()]);
            throw ValidationException::withMessages([$errorKey => 'These credentials could not be verified.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        app(Audit::class)->record('login', $request->user());

        return $request->user()->must_change_password
            ? redirect()->route('password.first-change')
            : redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        app(Audit::class)->record('logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
