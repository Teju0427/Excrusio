<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TimeSlot;
use App\Models\WaitlistEntry;
use App\Notifications\BookingNotification;
use App\Support\VenueClock;
use Illuminate\Support\Facades\Cache;

/**
 * Free hosting has no scheduler, so time-based tidying runs at most once a minute,
 * triggered by ordinary page visits (see RunBookingMaintenance middleware).
 * `php artisan bookings:maintain` runs the same thing on demand.
 */
class BookingMaintenance
{
    public function __construct(private BookingService $bookings, private WaitlistService $waitlist) {}

    public function runThrottled(): void
    {
        if (Cache::add('bookings:maintenance', true, 60)) {
            $this->run();
        }
    }

    /** @return array{holds: int, requests: int, completed: int} */
    public function run(): array
    {
        // 1. Unpaid holds past their 10 minutes are released.
        $expiredHolds = Booking::where('status', BookingStatus::AwaitingPayment->value)->where('hold_expires_at', '<', now())->get(['id', 'time_slot_id']);
        Booking::whereIn('id', $expiredHolds->pluck('id'))->update(['status' => BookingStatus::Expired->value, 'hold_expires_at' => null]);

        // 2. Hotel requests not answered within the deadline expire with a full refund.
        $overdue = Booking::where('status', BookingStatus::Pending->value)->where('response_due_at', '<', now())->get();
        foreach ($overdue as $booking) {
            $this->bookings->transition($booking, BookingStatus::Expired);
            $booking->forceFill(['response_due_at' => null, 'cancellation_reason' => 'The host did not respond in time.'])->save();
            $this->bookings->refund($booking, $booking->total);
            $booking->customer?->notify(new BookingNotification($booking, 'The host didn’t respond in time, so your request expired. You have been refunded in full (demo).'));
        }

        // 3. Confirmed bookings that have ended become completed (and can be reviewed later).
        $completed = 0;
        Booking::where('status', BookingStatus::Confirmed->value)->where('ends_at', '<', now()->addDay())
            ->with('listing.destination')->chunkById(200, function ($chunk) use (&$completed) {
                foreach ($chunk as $booking) {
                    if ($booking->ends_at->lessThan(VenueClock::now($booking->listing))) {
                        $booking->forceFill(['status' => BookingStatus::Completed])->save();
                        $completed++;
                    }
                }
            });

        // 4. Seats freed by expired holds or offers go to the waitlist.
        $slotIds = $expiredHolds->pluck('time_slot_id')->filter()
            ->merge(WaitlistEntry::where('status', 'offered')->where('offer_expires_at', '<=', now())->pluck('time_slot_id'))
            ->unique();
        foreach (TimeSlot::whereIn('id', $slotIds)->get() as $slot) {
            $this->waitlist->offerFreedSeats($slot);
        }

        return ['holds' => $expiredHolds->count(), 'requests' => $overdue->count(), 'completed' => $completed];
    }
}
