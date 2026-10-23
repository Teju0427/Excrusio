<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', ['type' => $request->query('type') === 'host' ? 'provider' : 'customer']);
    }

    public function store(RegisterRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $isHost = $data['account_type'] === 'provider';

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        $user->password = $data['password'];   // hashed automatically by the model cast
        // Role and status are set here on the server, never taken directly from the form.
        $user->forceFill([
            'role' => $isHost ? UserRole::Provider : UserRole::Customer,
            'status' => $isHost ? UserStatus::Pending : UserStatus::Active,
        ])->save();

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('auth.registered', $user, [], ['role' => $user->role->value]);

        return $isHost
            ? redirect()->route('host.pending')
            : redirect()->route('dashboard')->with('status', 'Welcome to Excursio, '.$user->name.'.');
    }
}
