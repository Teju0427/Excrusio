<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'email' => ['required', 'email', 'lowercase', 'max:160', Rule::unique('users')->ignore($user->id)],
        ]);

        if ($user->is_demo && $data['email'] !== $user->email) {
            return back()->withErrors(['email' => 'The email of a shared demo account can’t be changed.']);
        }

        $old = $user->only(['name', 'phone', 'email']);
        $user->fill($data)->save();
        $audit->log('profile.updated', $user, $old, $user->only(['name', 'phone', 'email']));

        return back()->with('status', 'Profile saved.');
    }

    public function password(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user->is_demo) {
            return back()->withErrors(['current_password' => 'The password of a shared demo account can’t be changed.']);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->forceFill(['password' => $request->password])->save();
        $audit->log('profile.password_changed', $user);

        return back()->with('status', 'Password updated.');
    }
}
