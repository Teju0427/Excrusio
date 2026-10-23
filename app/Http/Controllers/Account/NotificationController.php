<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** In-app notifications (stored in the database — no email/SMS cost). Works for every role. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.notifications', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    /** Mark one as read and follow its link. Only relative links are followed (no open redirect). */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
