<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        // "Sign in to book" links pass ?next=/stays/… so the guest returns to the same listing.
        $next = (string) $request->query('next');
        if (str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\')) {
            $request->session()->put('url.intended', url($next));
        }

        return view('auth.login', ['demoLogins' => config('excursio.demo_logins')]);
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();   // prevents session fixation
        $audit->log('auth.login', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    /** One-click sign-in to a seeded demo account (can be switched off with EXCURSIO_DEMO_LOGINS=false). */
    public function demo(Request $request, string $role, AuditLogger $audit): RedirectResponse
    {
        abort_unless(config('excursio.demo_logins'), 404);

        $email = [
            'customer' => 'guest@excursio.test',
            'provider' => 'host.alpine@excursio.test',
            'admin' => 'admin@excursio.test',
        ][$role] ?? abort(404);

        $user = User::where('email', $email)->where('is_demo', true)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('auth.demo_login', $user);

        return redirect()->intended(route('dashboard'))->with('status', 'Signed in as the demo '.UserRole::from($role)->label().'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
