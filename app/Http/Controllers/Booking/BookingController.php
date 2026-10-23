<?php

namespace App\Http\Controllers\Booking;

use App\Enums\BookingStatus;
use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Services\BookingService;
use App\Services\CancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guest-facing booking flow: reserve (10-minute hold) → checkout → confirmation → cancel.
 * Prices and totals are always recalculated on the server; only IDs, dates and counts are accepted.
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function storeStay(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->listing_type === ListingType::Stay, 404);
        $data = $request->validate([
            'stay_unit_id' => ['required', 'integer'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $unit = StayUnit::where('listing_id', $listing->id)->findOrFail($data['stay_unit_id']);

        try {
            $booking = $this->bookings->holdStay($request->user(), $unit, $data['check_in'], $data['check_out'], (int) $data['rooms'], (int) $data['guests'], $data['idempotency_key'] ?? null);
        } catch (BookingException $e) {
            return redirect($listing->url())->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('checkout.show', $booking);
    }

    public function storeSlot(Request $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->listing_type === ListingType::Stay, 404);
        $data = $request->validate([
            'time_slot_id' => ['required', 'integer'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $slot = TimeSlot::where('listing_id', $listing->id)->findOrFail($data['time_slot_id']);

        try {
            $booking = $this->bookings->holdSlot($request->user(), $slot, (int) $data['guests'], $data['idempotency_key'] ?? null);
        } catch (BookingException $e) {
            return redirect($listing->url())->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('checkout.show', $booking);
    }

    public function checkout(Request $request, Booking $booking, CancellationService $cancellation): View|RedirectResponse
    {
        abort_unless($booking->customer_id === $request->user()->id, 404);
        if ($booking->booking_group_id) {
            return redirect()->route('trip.checkout', $booking->group);
        }
        if ($booking->status !== BookingStatus::AwaitingPayment || $booking->hold_expires_at?->isPast()) {
            return redirect()->route('bookings.show', $booking)->withErrors(['booking' => $booking->status === BookingStatus::AwaitingPayment
                ? 'Your 10-minute hold expired, so the place was released. Please check availability again.' : 'This booking is already complete.']);
        }
        $booking->load('listing.destination', 'listing.coverImage', 'listing.activityDetail', 'stayUnit', 'timeSlot');

        return view('booking.checkout', [
            'booking' => $booking,
            'timeline' => $cancellation->timeline($booking->cancellation_policy, $booking->listing_type, $booking->starts_at, $booking->total),
        ]);
    }

    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->customer_id === $request->user()->id, 404);
        $details = $this->validateDetails($request, $booking);

        try {
            $booking = $this->bookings->checkout($booking, $details);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return redirect()->route('bookings.show', $booking)->with('status', $booking->status === BookingStatus::Pending
            ? 'Request sent! The host has '.config('excursio.approval_hours').' hours to confirm. You won’t be charged if they decline (demo).'
            : 'You’re booked! Your confirmation is below.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->can('cancel', $booking), 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $booking = $this->bookings->cancelByGuest($booking, $request->user(), $data['reason'] ?? null);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('bookings.show', $booking)->with('status', $booking->refund_amount
            ? 'Booking cancelled. '.money($booking->refund_amount).' will be refunded (demo).' : 'Booking cancelled.');
    }

    /** Shared by single and trip checkout. */
    public static function detailRules(string $prefix = ''): array
    {
        return [
            $prefix.'guest_name' => ['required', 'string', 'max:120'],
            $prefix.'guest_email' => ['required', 'email', 'max:160'],
            $prefix.'guest_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            $prefix.'special_requests' => ['nullable', 'string', 'max:500'],
            $prefix.'acknowledged' => ['nullable', 'boolean'],
            $prefix.'participants' => ['nullable', 'array', 'max:20'],
            $prefix.'participants.*.full_name' => ['required', 'string', 'max:120'],
            $prefix.'participants.*.age' => ['required', 'integer', 'min:0', 'max:110'],
        ];
    }

    private function validateDetails(Request $request, Booking $booking): array
    {
        $data = $request->validate(self::detailRules(), ['guest_phone.regex' => 'Please enter a valid phone number.']);
        $data['acknowledged'] = $request->boolean('acknowledged');
        $data['participants'] = array_values($data['participants'] ?? []);

        return $data;
    }
}
