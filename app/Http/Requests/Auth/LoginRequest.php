<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Tries to sign in. After 5 failed attempts for the same email + IP,
     * further attempts are blocked for a minute (brute-force protection).
     */
    public function authenticate(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            event(new Lockout($this));
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            // Same message whether the email exists or not, so accounts can't be discovered.
            throw ValidationException::withMessages(['email' => 'These details don’t match our records.']);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
