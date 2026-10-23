<?php

namespace App\Http\Controllers\Booking;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\TimeSlot;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Feature F2: join a full session's waitlist, and claim a place when it opens up. */
class WaitlistController extends Controller
{
    public function join(Request $request, TimeSlot $slot, WaitlistService $waitlist): RedirectResponse
    {
        $data = $request->validate(['guests' => ['required', 'integer', 'min:1', 'max:20']]);
        $listing = Listing::findOrFail($slot->listing_id);

        try {
            $entry = $waitlist->join($request->user(), $slot, (int) $data['guests']);
        } catch (BookingException $e) {
            return redirect($listing->url())->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect($listing->url())->with('status', "You’re #{$waitlist->position($entry)} on the waitlist for {$slot->starts_at->format('D j M, H:i')}. We’ll hold a place for 30 minutes if one opens up.");
    }

    public function claim(Request $request, WaitlistEntry $entry, WaitlistService $waitlist, BookingService $bookings): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 404);

        try {
            $booking = $waitlist->claim($entry, $bookings);
        } catch (BookingException $e) {
            return redirect()->route('account.dashboard')->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('checkout.show', $booking);
    }

    public function leave(Request $request, WaitlistEntry $entry, WaitlistService $waitlist): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $waitlist->leave($entry);

        return back()->with('status', 'You left the waitlist.');
    }
}
