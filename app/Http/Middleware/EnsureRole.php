<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:provider,admin').
 * Checked on the server for every request — hiding a link is never the only protection.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        abort_unless($user && in_array($user->role, $allowed, true), 403, 'You do not have access to this area.');

        return $next($request);
    }
}
