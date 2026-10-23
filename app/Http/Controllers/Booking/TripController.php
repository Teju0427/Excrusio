<?php

namespace App\Http\Controllers\Booking;

use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Services\BundleBookingService;
use App\Services\CancellationService;
use App\Services\TripCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Feature F1: the trip cart (stay + dinner + activity in one checkout). */
class TripController extends Controller
{
    public function index(TripCart $cart): View
    {
        $items = $cart->items();

        return view('booking.trip', ['items' => $items, 'clashes' => $cart->clashes($items), 'total' => $cart->total($items)]);
    }

    public function addStay(Request $request, Listing $listing, TripCart $cart): RedirectResponse
    {
        abort_unless($listing->listing_type === ListingType::Stay, 404);
        $data = $request->validate([
            'stay_unit_id' => ['required', 'integer'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $unit = StayUnit::where('listing_id', $listing->id)->findOrFail($data['stay_unit_id']);

        return $this->attempt(fn () => $cart->addStay($unit, $data['check_in'], $data['check_out'], (int) $data['rooms'], (int) $data['guests']), $listing);
    }

    public function addSlot(Request $request, Listing $listing, TripCart $cart): RedirectResponse
    {
        abort_if($listing->listing_type === ListingType::Stay, 404);
        $data = $request->validate(['time_slot_id' => ['required', 'integer'], 'guests' => ['required', 'integer', 'min:1', 'max:20']]);
        $slot = TimeSlot::where('listing_id', $listing->id)->where('status', 'open')->findOrFail($data['time_slot_id']);

        return $this->attempt(fn () => $cart->addSlot($slot, (int) $data['guests']), $listing);
    }

    public function remove(int $index, TripCart $cart): RedirectResponse
    {
        $cart->remove($index);

        return redirect()->route('trip.index')->with('status', 'Removed from your trip.');
    }

    public function hold(Request $request, BundleBookingService $bundle): RedirectResponse
    {
        try {
            $group = $bundle->hold($request->user());
        } catch (BookingException $e) {
            return redirect()->route('trip.index')->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('trip.checkout', $group);
    }

    public function checkout(Request $request, BookingGroup $group, CancellationService $cancellation): View|RedirectResponse
    {
        abort_unless($group->customer_id === $request->user()->id, 404);
        $bookings = $group->bookings()->with('listing.destination', 'listing.coverImage', 'listing.activityDetail', 'stayUnit')->orderBy('starts_at')->get();

        if ($bookings->contains(fn ($b) => $b->status->value !== 'awaiting_payment' || $b->hold_expires_at?->isPast())) {
            return redirect()->route('trip.show', $group);
        }

        return view('booking.trip-checkout', [
            'group' => $group,
            'bookings' => $bookings,
            'timelines' => $bookings->mapWithKeys(fn ($b) => [$b->id => $cancellation->timeline($b->cancellation_policy, $b->listing_type, $b->starts_at, $b->total)]),
        ]);
    }

    public function pay(Request $request, BookingGroup $group, BundleBookingService $bundle): RedirectResponse
    {
        abort_unless($group->customer_id === $request->user()->id, 404);
        $rules = collect(BookingController::detailRules())->only(['guest_name', 'guest_email', 'guest_phone', 'special_requests'])->all() + [
            'participants' => ['nullable', 'array'],
            'participants.*' => ['array', 'max:20'],
            'participants.*.*.full_name' => ['required', 'string', 'max:120'],
            'participants.*.*.age' => ['required', 'integer', 'min:0', 'max:110'],
            'acknowledged' => ['nullable', 'array'],
        ];
        $request->validate($rules, ['guest_phone.regex' => 'Please enter a valid phone number.']);
        $common = $request->only(['guest_name', 'guest_email', 'guest_phone', 'special_requests']);

        $details = [];
        foreach ($group->bookings as $booking) {
            $details[$booking->id] = $common + [
                'acknowledged' => $request->boolean("acknowledged.{$booking->id}"),
                'participants' => array_values($request->input("participants.{$booking->id}", [])),
            ];
        }

        try {
            $bundle->checkout($group, $details);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return redirect()->route('trip.show', $group)->with('status', 'Your trip is booked!');
    }

    public function show(Request $request, BookingGroup $group): View
    {
        abort_unless($group->customer_id === $request->user()->id, 404);

        return view('booking.trip-show', ['group' => $group, 'bookings' => $group->bookings()->with('listing.destination', 'listing.coverImage')->orderBy('starts_at')->get()]);
    }

    private function attempt(callable $add, Listing $listing): RedirectResponse
    {
        try {
            $add();
        } catch (BookingException $e) {
            return redirect($listing->url())->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('trip.index')->with('status', 'Added to your trip.');
    }
}
