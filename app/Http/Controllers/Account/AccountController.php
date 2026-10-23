<?php

namespace App\Http\Controllers\Account;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Support\VenueClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $bookings = $user->bookings();

        $with = ['listing:id,title,slug,listing_type,destination_id', 'listing.destination:id,name', 'listing.coverImage'];

        return view('account.dashboard', [
            'user' => $user,
            'next' => (clone $bookings)->with($with)->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
                ->where('ends_at', '>=', now())->orderBy('starts_at')->limit(3)->get(),
            'waitlist' => $user->waitlistEntries()->with('timeSlot.listing:id,title,slug,listing_type')
                ->whereIn('status', ['waiting', 'offered'])->whereHas('timeSlot', fn ($q) => $q->where('starts_at', '>', now()))->get(),
            'notifications' => $user->notifications()->limit(5)->get(),
            'upcomingCount' => (clone $bookings)->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
                ->where('starts_at', '>=', now())->count(),
            'pendingCount' => (clone $bookings)->where('status', BookingStatus::Pending->value)->count(),
            'pastCount' => (clone $bookings)->where('status', BookingStatus::Completed->value)->count(),
            'wishlistCount' => $user->wishlist()->count(),
        ]);
    }

    public const TABS = ['upcoming' => 'Upcoming', 'pending' => 'Awaiting host', 'past' => 'Past', 'cancelled' => 'Cancelled'];

    /** Only the signed-in customer's own bookings are ever queried. */
    public function bookings(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'upcoming';
        $base = $request->user()->bookings()->where('status', '!=', BookingStatus::AwaitingPayment->value);
        $scopes = [
            'upcoming' => fn ($q) => $q->where('status', BookingStatus::Confirmed->value)->where('ends_at', '>=', now()),
            'pending' => fn ($q) => $q->where('status', BookingStatus::Pending->value),
            'past' => fn ($q) => $q->where(fn ($w) => $w->where('status', BookingStatus::Completed->value)
                ->orWhere(fn ($c) => $c->where('status', BookingStatus::Confirmed->value)->where('ends_at', '<', now()))),
            'cancelled' => fn ($q) => $q->whereIn('status', [BookingStatus::Cancelled->value, BookingStatus::Rejected->value, BookingStatus::Expired->value]),
        ];
        $counts = collect($scopes)->map(fn ($scope) => $scope(clone $base)->count());
        $bookings = $scopes[$tab](clone $base)
            ->with(['listing:id,title,slug,listing_type,destination_id', 'listing.destination:id,name', 'listing.coverImage'])
            ->orderBy('starts_at', in_array($tab, ['upcoming', 'pending']) ? 'asc' : 'desc')
            ->paginate(10)->withQueryString();

        return view('account.bookings', ['bookings' => $bookings, 'tab' => $tab, 'tabs' => self::TABS, 'counts' => $counts]);
    }

    public function showBooking(Booking $booking): View
    {
        // BookingPolicy: the guest, the listing's host or an admin. Anyone else gets 404 (not 403),
        // so a guessed reference doesn't even reveal that the booking exists.
        abort_unless(request()->user()->can('view', $booking), 404);
        $booking->load(['listing.destination', 'listing.coverImage', 'stayUnit', 'timeSlot', 'participants', 'review']);
        $services = app(BookingService::class);
        $user = request()->user();

        return view('account.booking', [
            'booking' => $booking,
            'refund' => $services->refundPreview($booking),
            'reviewBlocker' => $booking->customer_id === $user->id && ! $booking->review
                ? app(\App\Services\ReviewService::class)->ineligibleReason($booking, $user) : null,
            'timeline' => app(CancellationService::class)->timeline(
                $booking->cancellation_policy, $booking->listing_type, $booking->starts_at, $booking->total, VenueClock::now($booking->listing)),
        ]);
    }
}
