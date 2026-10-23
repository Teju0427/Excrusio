<?php

namespace App\Services;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\BookingNotification;
use App\Payments\PaymentGateway;
use App\Support\VenueClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The booking engine.
 *
 * How double bookings are prevented:
 *   1. Every reservation runs inside a database transaction.
 *   2. The room type (stay_units row) or session (time_slots row) is locked with
 *      SELECT … FOR UPDATE, so two people booking the same thing queue up instead of racing.
 *   3. Availability is re-checked *after* taking the lock, then the booking is written.
 *   4. If anything fails, the transaction rolls back and nothing is half-saved.
 *
 * Flow: hold (10-minute reservation) → checkout (details + demo payment) →
 *       confirmed, or pending until the hotel approves (request-to-book stays).
 */
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private CancellationService $cancellation,
        private PaymentGateway $gateway,
        private AuditLogger $audit,
    ) {}

    // ===================================================================== HOLDS

    public function holdStay(User $customer, StayUnit $unit, string $checkIn, string $checkOut, int $rooms, int $guests, ?string $idempotencyKey = null): Booking
    {
        if ($existing = $this->findByIdempotencyKey($customer, $idempotencyKey)) {
            return $existing;
        }

        $booking = $this->transaction(function () use ($customer, $unit, $checkIn, $checkOut, $rooms, $guests, $idempotencyKey) {
            $unit = StayUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            return $this->reserveStay($unit, $checkIn, $checkOut, $rooms, $guests, [
                'customer' => $customer, 'idempotency_key' => $idempotencyKey,
            ]);
        });

        $this->audit->log('booking.held', $booking, [], ['total' => $booking->total]);

        return $booking;
    }

    public function holdSlot(User $customer, TimeSlot $slot, int $guests, ?string $idempotencyKey = null): Booking
    {
        if ($existing = $this->findByIdempotencyKey($customer, $idempotencyKey)) {
            return $existing;
        }

        $booking = $this->transaction(function () use ($customer, $slot, $guests, $idempotencyKey) {
            $slot = TimeSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();

            return $this->reserveSlot($slot, $guests, ['customer' => $customer, 'idempotency_key' => $idempotencyKey]);
        });

        $this->audit->log('booking.held', $booking, [], ['total' => $booking->total]);

        return $booking;
    }

    /**
     * Validates and writes a stay booking. The caller MUST already hold the lock on $unit.
     * Used by single bookings, the trip cart (F1) and host offline bookings.
     */
    public function reserveStay(StayUnit $unit, string $checkIn, string $checkOut, int $rooms, int $guests, array $opts): Booking
    {
        $listing = Listing::with('stayDetail', 'destination')->findOrFail($unit->listing_id);
        $this->assertBookable($listing, ListingType::Stay);
        abort_unless($unit->is_active, 404);

        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();
        $today = VenueClock::now($listing)->startOfDay();
        $nights = (int) $in->diffInDays($out, false);
        $detail = $listing->stayDetail;

        if ($in->lessThan($today)) {
            throw new BookingException('Check-in can’t be in the past.');
        }
        if ($nights < 1) {
            throw new BookingException('Check-out must be after check-in.');
        }
        if ($detail && ($nights < $detail->min_nights || $nights > $detail->max_nights)) {
            throw new BookingException("This stay takes bookings of {$detail->min_nights}–{$detail->max_nights} nights.");
        }
        if ($rooms < 1 || $rooms > $unit->quantity) {
            throw new BookingException("{$unit->name} has {$unit->quantity} ".Str::plural('room', $unit->quantity).' in total.');
        }
        if ($guests < 1 || $guests > $unit->max_guests * $rooms) {
            throw new BookingException("{$rooms} × {$unit->name} sleeps up to ".($unit->max_guests * $rooms).' guests.');
        }

        // Re-checked under the lock: this is what makes the last room safe.
        $left = $this->availability->roomsLeft($unit, $in, $out);
        if ($left < $rooms) {
            throw new BookingException($left === 0
                ? "Sorry — {$unit->name} was just booked for those dates."
                : "Sorry — only {$left} ".Str::plural('room', $left).' of this type '.($left === 1 ? 'is' : 'are').' left for those dates.');
        }

        $quote = $this->pricing->quoteStay($unit, $in, $out, $rooms);
        $checkInTime = substr($detail?->check_in_time ?? '14:00', 0, 5);
        $checkOutTime = substr($detail?->check_out_time ?? '11:00', 0, 5);

        return $this->writeBooking($listing, [
            'stay_unit_id' => $unit->id,
            'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(),
            'starts_at' => $in->toDateString().' '.$checkInTime.':00',
            'ends_at' => $out->toDateString().' '.$checkOutTime.':00',
            'nights' => $nights,
            'rooms' => $rooms,
            'guests' => $guests,
            'unit_price' => $quote['unit_price'],
            'subtotal' => $quote['subtotal'],
            'fees' => $quote['fees'],
            'total' => $quote['total'],
            'price_breakdown' => ['nights' => $quote['nights'], 'rooms' => $rooms, 'unit' => $unit->name],
        ], $opts);
    }

    /** Validates and writes a slot booking. The caller MUST already hold the lock on $slot. */
    public function reserveSlot(TimeSlot $slot, int $guests, array $opts): Booking
    {
        $listing = Listing::with('destination', 'activityDetail', 'diningDetail')->findOrFail($slot->listing_id);
        $this->assertBookable($listing, null);

        if ($slot->status !== 'open') {
            throw new BookingException('This session is no longer available.');
        }
        $earliest = VenueClock::now($listing)->addHours($this->availability->cutoffHours($listing));
        if ($slot->starts_at->lessThan($earliest)) {
            throw new BookingException('Online booking for this time has closed. Please choose a later time.');
        }
        $maxParty = $listing->diningDetail?->max_party_size;
        if ($guests < 1 || ($maxParty && $guests > $maxParty)) {
            throw new BookingException("Online reservations are for parties of 1–{$maxParty}.");
        }

        $remaining = $this->availability->slotRemaining($slot, $opts['customer']?->id ?? null);
        if ($remaining < $guests) {
            throw new BookingException($remaining === 0
                ? 'Sorry — this time is now full. You can join the waitlist.'
                : "Sorry — only {$remaining} ".($listing->listing_type === ListingType::Dining ? 'seats' : 'places').' left at this time.');
        }

        $quote = $this->pricing->quoteSlot($listing, $slot, $guests);

        return $this->writeBooking($listing, [
            'time_slot_id' => $slot->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'guests' => $guests,
            'unit_price' => $quote['unit_price'],
            'subtotal' => $quote['subtotal'],
            'fees' => $quote['fees'],
            'total' => $quote['total'],
            'price_breakdown' => ['per_person' => $quote['unit_price'], 'guests' => $guests],
        ], $opts);
    }

    // ================================================================== CHECKOUT

    /**
     * Saves guest details and completes the (demo) payment for a held booking.
     *
     * @param  array{guest_name: string, guest_email?: ?string, guest_phone?: ?string, special_requests?: ?string, acknowledged?: bool, participants?: list<array{full_name: string, age: int}>}  $details
     */
    public function checkout(Booking $booking, array $details): Booking
    {
        $booking = $this->transaction(function () use ($booking, $details) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertHoldIsLive($booking);
            $this->applyDetails($booking, $details);

            $charge = null;
            if ($booking->total > 0) {
                $charge = $this->gateway->charge($booking->total, 'Excursio '.$booking->reference, ['booking' => $booking->reference]);
                if ($charge['status'] !== 'succeeded') {
                    throw new BookingException('The payment did not go through. Nothing was charged; please try again.');
                }
                $this->recordPayment($booking, null, 'charge', $booking->total, $charge);
            }

            $this->finalise($booking, $charge !== null);

            return $booking;
        });

        $this->audit->log('booking.'.$booking->status->value, $booking, ['status' => 'awaiting_payment'], ['status' => $booking->status->value, 'payment' => $booking->payment_status->value]);
        $this->notifyAfterCheckout($booking);

        return $booking;
    }

    /** Moves a paid (or free) booking to its next status. Shared with the trip-cart checkout. */
    public function finalise(Booking $booking, bool $paid): void
    {
        $listing = $booking->listing()->first();
        $next = $listing->booking_mode === BookingMode::Request && $booking->listing_type === ListingType::Stay
            ? BookingStatus::Pending
            : BookingStatus::Confirmed;

        $this->transition($booking, $next);
        $booking->forceFill([
            'payment_status' => $paid ? PaymentStatus::DemoPaid : PaymentStatus::NotRequired,
            'hold_expires_at' => null,
            'response_due_at' => $next === BookingStatus::Pending ? now()->addHours(config('excursio.approval_hours')) : null,
            'confirmed_at' => $next === BookingStatus::Confirmed ? now() : null,
            'checkin_token' => $next === BookingStatus::Confirmed ? Str::random(40) : null,
        ])->save();
    }

    public function applyDetails(Booking $booking, array $details): void
    {
        $listing = Listing::with('activityDetail')->findOrFail($booking->listing_id);

        if ($booking->listing_type === ListingType::Activity) {
            $people = $details['participants'] ?? [];
            if (count($people) !== $booking->guests) {
                throw new BookingException('Please add the name and age of every participant.');
            }
            $minAge = $listing->activityDetail?->min_age;
            foreach ($people as $p) {
                if ($minAge && (int) $p['age'] < $minAge) {
                    throw new BookingException("Every participant must be at least {$minAge} years old for this activity.");
                }
            }
            if (empty($details['acknowledged'])) {
                throw new BookingException('Please confirm that everyone meets the safety and eligibility requirements.');
            }
            $booking->participants()->delete();
            foreach ($people as $p) {
                $booking->participants()->create(['full_name' => $p['full_name'], 'age' => (int) $p['age']]);
            }
            $booking->eligibility_acknowledged_at = now();
        }

        $booking->fill([
            'guest_name' => $details['guest_name'],
            'guest_email' => $details['guest_email'] ?? $booking->guest_email,
            'guest_phone' => $details['guest_phone'] ?? null,
            'special_requests' => $details['special_requests'] ?? null,
        ]);
    }

    // ============================================================ HOST DECISIONS

    public function confirmRequest(Booking $booking, User $actor): Booking
    {
        $booking = $this->transaction(function () use ($booking) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status === BookingStatus::Pending && $booking->response_due_at?->isPast()) {
                throw new BookingException('This request expired before it was answered, and the guest was refunded.');
            }
            $this->transition($booking, BookingStatus::Confirmed);
            $booking->forceFill(['confirmed_at' => now(), 'response_due_at' => null, 'checkin_token' => Str::random(40)])->save();

            return $booking;
        });

        $this->audit->log('booking.confirmed', $booking, ['status' => 'pending'], ['status' => 'confirmed']);
        $this->notifyCustomer($booking, 'Good news — your stay at '.$booking->listing->title.' is confirmed.');

        return $booking;
    }

    public function rejectRequest(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        $booking = $this->transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->transition($booking, BookingStatus::Rejected);
            $booking->forceFill(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason, 'response_due_at' => null])->save();
            $this->refund($booking, $booking->total);   // declined requests are always refunded in full

            return $booking;
        });

        $this->audit->log('booking.rejected', $booking, ['status' => 'pending'], ['status' => 'rejected', 'refund' => $booking->refund_amount]);
        $this->notifyCustomer($booking, $booking->listing->title.' couldn’t accept your request. You have been refunded in full (demo).');

        return $booking;
    }

    // ============================================================ CANCELLATIONS

    /** @return array{percent: int, amount: int} what the guest would get back right now */
    public function refundPreview(Booking $booking): array
    {
        return match ($booking->status) {
            BookingStatus::Pending => ['percent' => 100, 'amount' => $booking->total],   // host hasn't accepted yet
            BookingStatus::Confirmed => $this->cancellation->refundFor($booking),
            default => ['percent' => 0, 'amount' => 0],
        };
    }

    public function cancelByGuest(Booking $booking, User $guest, ?string $reason = null): Booking
    {
        $booking = $this->transaction(function () use ($booking, $guest, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw new BookingException('This booking can no longer be cancelled.');
            }
            if ($booking->status === BookingStatus::Confirmed && $booking->starts_at->lessThanOrEqualTo(VenueClock::now($booking->listing))) {
                throw new BookingException('This booking has already started and can’t be cancelled online.');
            }
            $refund = $this->refundPreview($booking);
            $this->transition($booking, BookingStatus::Cancelled);
            $booking->forceFill(['cancelled_at' => now(), 'cancelled_by' => $guest->id, 'cancellation_reason' => $reason, 'hold_expires_at' => null, 'response_due_at' => null])->save();
            $this->refund($booking, $refund['amount']);

            return $booking;
        });

        $this->audit->log('booking.cancelled', $booking, [], ['by' => 'guest', 'refund' => $booking->refund_amount]);
        $this->afterSeatsFreed($booking);

        return $booking;
    }

    /** Host/weather cancellation: always a full refund. */
    public function cancelByHost(Booking $booking, User $actor, string $reason): Booking
    {
        $booking = $this->transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                return $booking;
            }
            $this->transition($booking, BookingStatus::Cancelled);
            $booking->forceFill(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason, 'hold_expires_at' => null, 'response_due_at' => null])->save();
            $this->refund($booking, $booking->total);

            return $booking;
        });

        $by = $actor->isAdmin() ? 'admin' : 'host';
        $this->audit->log('booking.cancelled', $booking, [], ['by' => $by, 'reason' => $reason, 'refund' => $booking->refund_amount]);
        $this->notifyCustomer($booking, $booking->listing->title.' was cancelled by '.($by === 'admin' ? 'Excursio support' : 'the host').' ('.$reason.'). You have been refunded in full (demo).');

        return $booking;
    }

    /**
     * Weather cancellation (F4): closes the session and cancels every booking on it with a full refund.
     *
     * @return int number of bookings cancelled
     */
    public function cancelSlotForWeather(TimeSlot $slot, User $actor, string $reason = 'Unsafe weather'): int
    {
        $bookings = DB::transaction(function () use ($slot, $reason) {
            $slot = TimeSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $slot->forceFill(['status' => 'cancelled', 'cancellation_reason' => $reason])->save();
            WaitlistEntry::where('time_slot_id', $slot->id)->whereIn('status', ['waiting', 'offered'])->update(['status' => 'expired']);

            return Booking::where('time_slot_id', $slot->id)->whereIn('status', ['awaiting_payment', 'pending', 'confirmed'])->get();
        });

        foreach ($bookings as $booking) {
            $this->cancelByHost($booking, $actor, $reason);
        }
        $this->audit->log('slot.weather_cancelled', $slot, [], ['bookings' => $bookings->count()]);

        return $bookings->count();
    }

    // ========================================================== OFFLINE BOOKINGS

    /** Phone / walk-in bookings added by the host. They use the same inventory and locks. */
    public function createOffline(User $host, Listing $listing, array $data): Booking
    {
        $opts = ['customer' => null, 'offline' => true, 'guest_name' => $data['guest_name'], 'guest_phone' => $data['guest_phone'] ?? null, 'special_requests' => $data['notes'] ?? null];

        $booking = $this->transaction(function () use ($listing, $data, $opts) {
            if ($listing->listing_type === ListingType::Stay) {
                $unit = StayUnit::where('listing_id', $listing->id)->whereKey($data['stay_unit_id'])->lockForUpdate()->firstOrFail();
                $booking = $this->reserveStay($unit, $data['check_in'], $data['check_out'], (int) $data['rooms'], (int) $data['guests'], $opts);
            } else {
                $slot = TimeSlot::where('listing_id', $listing->id)->whereKey($data['time_slot_id'])->lockForUpdate()->firstOrFail();
                $booking = $this->reserveSlot($slot, (int) $data['guests'], $opts);
            }
            $this->transition($booking, BookingStatus::Confirmed);
            $booking->forceFill([
                'payment_status' => PaymentStatus::NotRequired, 'hold_expires_at' => null,
                'confirmed_at' => now(), 'checkin_token' => Str::random(40),
            ])->save();

            return $booking;
        });

        $this->audit->log('booking.offline_created', $booking, [], ['by' => $host->id]);

        return $booking;
    }

    // ================================================================ INTERNALS

    /**
     * QR / reference check-in (F3). Allowed for confirmed bookings from 12 hours before the start
     * until the end, in the venue's local time. Checking in twice is harmless.
     */
    public function checkIn(Booking $booking, User $host): Booking
    {
        $booking = $this->transaction(function () use ($booking, $host) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->checked_in_at) {
                return $booking;
            }
            if ($booking->status !== BookingStatus::Confirmed) {
                throw new BookingException('Only confirmed bookings can be checked in (this one is '.mb_strtolower($booking->status->label()).').');
            }
            $now = VenueClock::now($booking->listing);
            if ($now->lessThan($booking->starts_at->copy()->subHours(12))) {
                throw new BookingException('Too early — check-in opens 12 hours before '.$booking->starts_at->format('D j M, H:i').'.');
            }
            if ($now->greaterThan($booking->ends_at)) {
                throw new BookingException('This booking has already ended.');
            }
            $booking->forceFill(['checked_in_at' => now(), 'checked_in_by' => $host->id])->save();

            return $booking;
        });

        if ($booking->wasChanged('checked_in_at')) {
            $this->audit->log('booking.checked_in', $booking, [], ['by' => $host->id]);
        }

        return $booking;
    }

    public function transition(Booking $booking, BookingStatus $next): void
    {
        if (! $booking->status->canTransitionTo($next)) {
            throw new BookingException("A booking that is {$booking->status->label()} can’t become {$next->label()}.");
        }
        $booking->status = $next;
    }

    public function refund(Booking $booking, int $amount): void
    {
        $paid = $booking->payment_status === PaymentStatus::DemoPaid;
        $booking->refund_amount = $paid ? $amount : 0;

        if ($paid && $amount > 0) {
            $charge = $booking->payments()->where('type', 'charge')->latest('id')->first()
                ?? Payment::where('booking_group_id', $booking->booking_group_id)->where('type', 'charge')->whereNotNull('booking_group_id')->latest('id')->first();
            $result = $this->gateway->refund($charge?->gateway_reference ?? 'n/a', $amount);
            $this->recordPayment($booking, null, 'refund', $amount, $result);
            $booking->payment_status = $amount >= $booking->total ? PaymentStatus::DemoRefunded : PaymentStatus::DemoPartiallyRefunded;
        }
        $booking->save();
    }

    public function recordPayment(?Booking $booking, ?int $groupId, string $type, int $amount, array $result): Payment
    {
        return Payment::create([
            'booking_id' => $booking?->id,
            'booking_group_id' => $groupId,
            'gateway' => $this->gateway->name(),
            'gateway_reference' => $result['reference'],
            'type' => $type,
            'amount' => $amount,
            'status' => $result['status'],
            'meta' => $result['meta'] ?? null,
        ]);
    }

    public function assertHoldIsLive(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::AwaitingPayment) {
            throw new BookingException('This booking has already been completed or cancelled.');
        }
        if ($booking->hold_expires_at?->isPast()) {
            $booking->forceFill(['status' => BookingStatus::Expired, 'hold_expires_at' => null])->save();
            throw new BookingException('Your 10-minute hold expired, so the place was released. Please check availability again.');
        }
    }

    private function writeBooking(Listing $listing, array $attrs, array $opts): Booking
    {
        /** @var User|null $customer */
        $customer = $opts['customer'] ?? null;

        $booking = new Booking([
            'guest_name' => $opts['guest_name'] ?? $customer?->name ?? 'Guest',
            'guest_email' => $customer?->email,
            'guest_phone' => $opts['guest_phone'] ?? $customer?->phone,
            'special_requests' => $opts['special_requests'] ?? null,
        ]);
        $booking->forceFill(array_merge($attrs, [
            'reference' => $this->newReference($listing->listing_type),
            'source' => ! empty($opts['offline']) ? 'offline' : 'online',
            'booking_group_id' => $opts['group_id'] ?? null,
            'customer_id' => $customer?->id,
            'listing_id' => $listing->id,
            'listing_type' => $listing->listing_type,
            'cancellation_policy' => $listing->cancellation_policy,   // snapshot
            'status' => BookingStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Unpaid,
            'hold_expires_at' => now()->addMinutes(config('excursio.hold_minutes')),
            'idempotency_key' => $opts['idempotency_key'] ?? null,
        ]))->save();

        return $booking;
    }

    private function assertBookable(Listing $listing, ?ListingType $type): void
    {
        if ($listing->status !== ListingStatus::Published || ($type && $listing->listing_type !== $type)) {
            throw new BookingException('This listing is not taking bookings right now.');
        }
    }

    private function findByIdempotencyKey(User $customer, ?string $key): ?Booking
    {
        return $key ? Booking::where('idempotency_key', $key)->where('customer_id', $customer->id)->first() : null;
    }

    /** Short, unambiguous references like EXC-ST-7K3P9Q (no 0/O or 1/I). */
    private function newReference(ListingType $type): string
    {
        do {
            $ref = 'EXC-'.$type->code().'-'.collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (Booking::where('reference', $ref)->exists());

        return $ref;
    }

    /** Retries automatically if MySQL picks this transaction as a deadlock victim. */
    private function transaction(callable $callback): mixed
    {
        return DB::transaction($callback, attempts: 3);
    }

    private function afterSeatsFreed(Booking $booking): void
    {
        if ($booking->time_slot_id) {
            app(WaitlistService::class)->offerFreedSeats(TimeSlot::find($booking->time_slot_id));
        }
    }

    private function notifyAfterCheckout(Booking $booking): void
    {
        $booking->loadMissing('listing.provider');
        if ($booking->status === BookingStatus::Pending) {
            $booking->listing->provider->notify(new BookingNotification($booking, 'New booking request for '.$booking->listing->title.' — please respond within '.config('excursio.approval_hours').' hours.'));
            $this->notifyCustomer($booking, 'Your request for '.$booking->listing->title.' was sent. The host will reply within '.config('excursio.approval_hours').' hours.');
        } else {
            $booking->listing->provider->notify(new BookingNotification($booking, 'New booking: '.$booking->listing->title.' ('.$booking->reference.').'));
            $this->notifyCustomer($booking, 'You’re booked! '.$booking->listing->title.' is confirmed.');
        }
    }

    private function notifyCustomer(Booking $booking, string $message): void
    {
        $booking->loadMissing('customer');
        $booking->customer?->notify(new BookingNotification($booking, $message));
    }
}
