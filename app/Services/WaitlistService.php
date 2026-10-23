<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistOfferNotification;
use App\Support\VenueClock;
use Illuminate\Support\Facades\DB;

/**
 * Feature F2 — waitlist for full sessions.
 *
 * When seats free up (a cancellation, an expired hold), the first person in the queue whose
 * party fits gets an offer. Those seats are held for them for 30 minutes; if they don't claim
 * in time, the offer passes to the next person. No cron needed: expiry is checked whenever
 * the slot is read (and by BookingMaintenance).
 */
class WaitlistService
{
    public const OFFER_MINUTES = 30;

    public function __construct(private AvailabilityService $availability) {}

    public function join(User $user, TimeSlot $slot, int $partySize): WaitlistEntry
    {
        $listing = Listing::with('destination', 'diningDetail')->findOrFail($slot->listing_id);
        if ($slot->status !== 'open' || $slot->starts_at->lessThan(VenueClock::now($listing))) {
            throw new BookingException('This session is no longer available.');
        }
        if ($this->availability->slotRemaining($slot, $user->id) >= $partySize) {
            throw new BookingException('Good news — there is space at this time, so you can book it directly.');
        }
        $max = $listing->diningDetail?->max_party_size ?? 20;
        if ($partySize < 1 || $partySize > $max) {
            throw new BookingException("Party size must be between 1 and {$max}.");
        }

        $entry = WaitlistEntry::firstOrNew(['time_slot_id' => $slot->id, 'user_id' => $user->id]);
        $entry->party_size = $partySize;
        $entry->forceFill(['status' => 'waiting', 'offered_at' => null, 'offer_expires_at' => null])->save();

        return $entry;
    }

    public function leave(WaitlistEntry $entry): void
    {
        $entry->forceFill(['status' => 'left'])->save();
        $this->offerFreedSeats($entry->timeSlot);
    }

    public function position(WaitlistEntry $entry): int
    {
        return WaitlistEntry::where('time_slot_id', $entry->time_slot_id)->whereIn('status', ['waiting', 'offered'])
            ->where('created_at', '<=', $entry->created_at)->where('id', '<=', $entry->id)->count();
    }

    /** Expire stale offers, then offer currently-free seats to the queue, in order. */
    public function offerFreedSeats(?TimeSlot $slot): void
    {
        if (! $slot || $slot->status !== 'open') {
            return;
        }

        $offers = DB::transaction(function () use ($slot) {
            TimeSlot::whereKey($slot->id)->lockForUpdate()->first();
            WaitlistEntry::where('time_slot_id', $slot->id)->where('status', 'offered')
                ->where('offer_expires_at', '<=', now())->update(['status' => 'expired']);

            $free = $this->availability->slotRemaining($slot);   // already excludes seats held by live offers
            $made = [];
            $queue = WaitlistEntry::with('user', 'timeSlot')->where('time_slot_id', $slot->id)->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->get();
            foreach ($queue as $entry) {
                if ($entry->party_size <= $free) {
                    $entry->forceFill(['status' => 'offered', 'offered_at' => now(), 'offer_expires_at' => now()->addMinutes(self::OFFER_MINUTES)])->save();
                    $free -= $entry->party_size;
                    $made[] = $entry;
                }
            }

            return $made;
        });

        $title = Listing::whereKey($slot->listing_id)->value('title');
        foreach ($offers as $entry) {
            $entry->user->notify(new WaitlistOfferNotification($entry, $title));
        }
    }

    /** Turns a live offer into a normal 10-minute booking hold. */
    public function claim(WaitlistEntry $entry, BookingService $bookings): Booking
    {
        if ($entry->status !== 'offered' || $entry->offer_expires_at?->isPast()) {
            throw new BookingException('This offer has expired. You can join the waitlist again if the session is still full.');
        }
        $booking = $bookings->holdSlot($entry->user, $entry->timeSlot, $entry->party_size);
        $entry->forceFill(['status' => 'claimed'])->save();

        return $booking;
    }
}
