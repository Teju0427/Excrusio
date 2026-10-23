<?php

namespace App\Http\Controllers\Host;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F3 — QR check-in. The guest's QR code encodes /host/check-in/{token}. Only the host who owns
 * the listing can open it; for everyone else the booking simply doesn't exist (404).
 */
class CheckInController extends Controller
{
    public function index(Request $request): View
    {
        $listingIds = $request->user()->listings()->pluck('id');

        return view('host.check-in', [
            'today' => Booking::whereIn('listing_id', $listingIds)->where('status', 'confirmed')
                ->whereBetween('starts_at', [now()->subHours(12), now()->addHours(36)])
                ->with('listing:id,title,listing_type')->orderBy('starts_at')->limit(40)->get(),
        ]);
    }

    /** Manual entry: a booking reference typed in, or the text of a scanned code. */
    public function find(Request $request): RedirectResponse
    {
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:200']])['code']);
        // A scanned QR contains the full check-in URL; take the token from its last segment.
        if (preg_match('~/host/check-in/([A-Za-z0-9]{40})/?$~', $code, $m)) {
            return redirect()->route('host.check-in.show', $m[1]);
        }
        $booking = Booking::where('reference', strtoupper($code))
            ->whereHas('listing', fn ($q) => $q->where('provider_id', $request->user()->id))->first();

        if (! $booking || ! $booking->checkin_token) {
            return back()->withErrors(['code' => 'No confirmed booking with that code at your listings.'])->withInput();
        }

        return redirect()->route('host.check-in.show', $booking->checkin_token);
    }

    public function show(Request $request, string $token): View
    {
        return view('host.check-in-show', ['booking' => $this->resolve($request, $token)]);
    }

    public function store(Request $request, string $token, BookingService $bookings): RedirectResponse
    {
        $booking = $this->resolve($request, $token);
        try {
            $already = (bool) $booking->checked_in_at;
            $bookings->checkIn($booking, $request->user());
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('host.check-in.show', $token)
            ->with('status', $already ? $booking->guest_name.' was already checked in.' : $booking->guest_name.' is checked in.');
    }

    private function resolve(Request $request, string $token): Booking
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);
        $booking = Booking::where('checkin_token', $token)->with('listing.destination', 'stayUnit', 'timeSlot')->first();
        abort_unless($booking && $request->user()->can('manage', $booking), 404);

        return $booking;
    }
}
