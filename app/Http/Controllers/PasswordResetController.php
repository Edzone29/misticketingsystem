<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.password-email');
    }

    public function firstLoginForm(Request $request)
    {
        abort_unless($request->user()->must_change_password, 404);

        return view('auth.first-password');
    }

    public function firstLoginUpdate(Request $request, Audit $audit)
    {
        abort_unless($request->user()->must_change_password, 404);
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $audit->record('user.first_password_set', $request->user());

        return redirect()->route('dashboard')->with('success', 'Your password has been set.');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink($request->only('email'));

        if (config('mail.default') === 'log') {
            return back()->with('status', 'If the address belongs to an account, the reset link was written to storage/logs/laravel.log for local testing. No email was delivered. Open the link on the computer running this local system within 60 minutes.');
        }

        return back()->with('status', 'If the address belongs to an account, a password reset link has been sent.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.password-reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request, Audit $audit)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $status = Password::reset($data, function ($user, string $password) use ($audit) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            $audit->record('user.password_reset', $user);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been reset. Sign in with the new password.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
