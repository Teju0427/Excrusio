<?php

namespace App\Http\Controllers\Host;

use App\Enums\BookingStatus;
use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\TimeSlot;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Host side of bookings: answer requests, add offline bookings, cancel a session for weather. */
class BookingRequestController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request): View
    {
        $listingIds = $request->user()->listings()->pluck('id');
        $base = Booking::whereIn('listing_id', $listingIds)->with('listing:id,title,slug,listing_type', 'stayUnit:id,name');

        return view('host.bookings', [
            'pending' => (clone $base)->where('status', BookingStatus::Pending->value)->orderBy('response_due_at')->get(),
            'upcoming' => (clone $base)->where('status', BookingStatus::Confirmed->value)->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(50)->get(),
            'recent' => (clone $base)->whereIn('status', ['cancelled', 'rejected', 'expired', 'completed'])->latest('updated_at')->limit(15)->get(),
            'listings' => $request->user()->listings()->with('stayUnits:id,listing_id,name,quantity')->orderBy('title')->get(),
        ]);
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->can('manage', $booking), 404);

        return $this->attempt(fn () => $this->bookings->confirmRequest($booking, $request->user()), 'Booking '.$booking->reference.' confirmed.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->can('manage', $booking), 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->bookings->rejectRequest($booking, $request->user(), $data['reason'] ?? null), 'Request declined. The guest has been refunded in full (demo).');
    }

    public function createOffline(Request $request, Listing $listing): View
    {
        abort_unless($request->user()->can('update', $listing), 404);
        $listing->load('stayUnits', 'destination');
        $slots = $listing->listing_type === ListingType::Stay ? collect()
            : $listing->timeSlots()->where('status', 'open')->where('starts_at', '>=', now()->toDateString())->orderBy('starts_at')->limit(120)->get();

        return view('host.offline', compact('listing', 'slots'));
    }

    public function storeOffline(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()->can('update', $listing), 404);
        $isStay = $listing->listing_type === ListingType::Stay;
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'guests' => ['required', 'integer', 'min:1', 'max:50'],
            'stay_unit_id' => [$isStay ? 'required' : 'prohibited', 'integer'],
            'check_in' => [$isStay ? 'required' : 'prohibited', 'date_format:Y-m-d'],
            'check_out' => [$isStay ? 'required' : 'prohibited', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => [$isStay ? 'required' : 'prohibited', 'integer', 'min:1', 'max:20'],
            'time_slot_id' => [$isStay ? 'prohibited' : 'required', 'integer'],
        ]);

        try {
            $booking = $this->bookings->createOffline($request->user(), $listing, $data);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return redirect()->route('host.bookings')->with('status', "Offline booking {$booking->reference} added for {$booking->guest_name}. Online availability has been updated.");
    }

    public function weatherCancel(Request $request, TimeSlot $slot): RedirectResponse
    {
        $listing = Listing::with('activityDetail')->findOrFail($slot->listing_id);
        abort_unless($request->user()->can('update', $listing) && $listing->listing_type === ListingType::Activity, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:120']]);

        $count = $this->bookings->cancelSlotForWeather($slot, $request->user(), $data['reason'] ?? 'Unsafe weather');

        return back()->with('status', "Session cancelled for weather. {$count} ".str('booking')->plural($count).' refunded in full (demo); guests can rebook another session.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
