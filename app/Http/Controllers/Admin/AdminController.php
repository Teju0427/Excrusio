<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Report;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(AnalyticsService $analytics): View
    {
        return view('admin.dashboard', [
            'stats' => $analytics->admin(),
            'pendingHosts' => User::where('role', UserRole::Provider->value)->where('status', UserStatus::Pending->value)->count(),
            'openReports' => Report::where('status', 'open')->count(),
            'pendingReviews' => \App\Models\Review::where('status', 'pending')->count(),
            'unreadMessages' => ContactMessage::whereNull('read_at')->count(),
            'recentAudit' => AuditLog::with('user:id,name')->where('action', 'not like', 'auth.%')->latest('created_at')->limit(8)->get(),
        ]);
    }

    public function audit(Request $request): View
    {
        $filters = $request->validate(['action' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_.]+$/']]);

        return view('admin.audit', [
            'logs' => AuditLog::with('user:id,name,role')
                ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->latest('created_at')->latest('id')->paginate(30)->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
        ]);
    }

    public function users(Request $request): View
    {
        $filters = $request->validate([
            'role' => ['nullable', 'in:customer,provider,admin'],
            'status' => ['nullable', 'in:active,pending,suspended'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $users = User::query()
            ->when($filters['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->orderByRaw("status = 'pending' desc")->latest()->paginate(20)->withQueryString();

        return view('admin.users', compact('users', 'filters'));
    }

    /** Approve a pending host, or suspend / reactivate an account. */
    public function updateStatus(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);

        abort_if($user->is($request->user()), 422, 'You cannot change your own status.');
        abort_if($user->isAdmin(), 422, 'Administrator accounts cannot be suspended here.');
        // Shared demo accounts stay usable for every visitor.
        abort_if($user->is_demo, 422, 'Shared demo accounts cannot be suspended.');

        $old = $user->status->value;
        $user->forceFill(['status' => UserStatus::from($data['status'])])->save();

        $action = $old === 'pending' && $data['status'] === 'active' ? 'user.approved' : 'user.status_changed';
        $audit->log($action, $user, ['status' => $old], ['status' => $data['status']]);

        $message = match (true) {
            $action === 'user.approved' => "{$user->name} is approved and can now list places.",
            $data['status'] === 'suspended' => "{$user->name} has been suspended.",
            default => "{$user->name} is active again.",
        };

        return back()->with('status', $message);
    }
}
