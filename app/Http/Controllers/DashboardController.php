<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /dashboard sends each person to the right home for their role. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            UserRole::Admin => redirect()->route('admin.dashboard'),
            UserRole::Provider => redirect()->route('host.dashboard'),
            UserRole::Customer => redirect()->route('account.dashboard'),
        };
    }
}
