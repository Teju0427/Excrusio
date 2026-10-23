<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingAdminController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'type' => ['nullable', Rule::enum(ListingType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $bookings = Booking::query()
            ->with('listing:id,title,slug,listing_type,destination_id', 'listing.destination:id,name')
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$t}%")
                ->orWhere('guest_name', 'like', "%{$t}%")->orWhere('guest_email', 'like', "%{$t}%")))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('listing_type', $t))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('starts_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('starts_at', '<=', $d.' 23:59:59'))
            ->latest('created_at')->paginate(25)->withQueryString();

        return view('admin.bookings', ['bookings' => $bookings, 'filters' => $f, 'statuses' => BookingStatus::cases(), 'types' => ListingType::cases()]);
    }

    /** Support cancellation: always a full (demo) refund, logged against the admin. */
    public function cancel(Request $request, Booking $booking, BookingService $service): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']])['reason'];
        try {
            if (! in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw new BookingException('Only active bookings can be cancelled.');
            }
            $service->cancelByHost($booking, $request->user(), $reason);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "{$booking->reference} cancelled with a full refund (demo). The guest has been notified.");
    }
}
