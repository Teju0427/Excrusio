<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended users are signed out immediately.
 * Hosts waiting for approval are sent to a "pending approval" page instead of host tools.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->status === UserStatus::Suspended) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been suspended. Please contact support.']);
        }

        if ($user?->status === UserStatus::Pending) {
            return redirect()->route('host.pending');
        }

        return $next($request);
    }
}
