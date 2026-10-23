<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Notifications\SimpleNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public const TABS = ['pending_review' => 'Waiting for review', 'published' => 'Published', 'rejected' => 'Rejected', 'archived' => 'Unpublished'];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'pending_review';

        return view('admin.listings', [
            'status' => $status,
            'tabs' => self::TABS,
            'counts' => Listing::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'listings' => Listing::where('status', $status)
                ->with('provider:id,name,email', 'destination:id,name', 'category:id,name', 'coverImage')
                ->withCount(['stayUnits', 'schedules'])
                ->orderBy($status === 'pending_review' ? 'updated_at' : 'title')->paginate(20)->withQueryString(),
        ]);
    }

    /** approve → published · reject (reason required) · unpublish → archived (hidden; bookings kept). */
    public function update(Request $request, Listing $listing, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('moderate', Listing::class), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,unpublish'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ], ['reason.required_if' => 'Tell the host why, so they can fix it.']);

        $old = $listing->status->value;
        [$status, $message, $note] = match ($data['decision']) {
            'approve' => [ListingStatus::Published, "“{$listing->title}” is now live.", "Your listing “{$listing->title}” was approved and is now live."],
            'reject' => [ListingStatus::Rejected, "“{$listing->title}” was sent back to the host.", "Your listing “{$listing->title}” needs changes: {$data['reason']}"],
            'unpublish' => [ListingStatus::Archived, "“{$listing->title}” is hidden from guests. Existing bookings still stand.", "Your listing “{$listing->title}” was unpublished by Excursio. Existing bookings still stand."],
        };

        $listing->forceFill([
            'status' => $status,
            'rejection_reason' => $data['decision'] === 'reject' ? $data['reason'] : null,
            'published_at' => $status === ListingStatus::Published ? ($listing->published_at ?? now()) : $listing->published_at,
        ])->save();

        $audit->log(['approve' => 'listing.approved', 'reject' => 'listing.rejected', 'unpublish' => 'listing.unpublished'][$data['decision']], $listing, ['status' => $old], ['status' => $status->value, 'reason' => $data['reason'] ?? null]);
        $listing->provider->notify(new SimpleNotification($note, route('host.listings.edit', $listing, false)));

        return back()->with('status', $message);
    }
}
