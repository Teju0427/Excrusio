<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:160']]);

        // Demo accounts are shared, so their passwords can't be reset by visitors.
        if (User::where('email', $request->email)->where('is_demo', true)->exists()) {
            return back()->withErrors(['email' => 'Demo account passwords can’t be reset. Use the demo sign-in buttons instead.']);
        }

        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email exists, so accounts can't be discovered.
        return back()->with('status', 'If an account exists for that email, a reset link has been sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
