<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Feature F1 — books every item of a trip in ONE transaction: all succeed, or none are booked.
 * Rows are locked in a fixed order (room types by id, then sessions by id) so two people
 * checking out overlapping trips at the same moment can't deadlock each other.
 */
class BundleBookingService
{
    public function __construct(
        private BookingService $bookings,
        private TripCart $cart,
        private PaymentGateway $gateway,
        private AuditLogger $audit,
    ) {}

    public function hold(User $customer): BookingGroup
    {
        $items = $this->cart->items();
        if ($items->count() < 2) {
            throw new BookingException('Add at least two items to book them together as a trip.');
        }
        if ($clashes = $this->cart->clashes($items)) {
            throw new BookingException($clashes[0]);
        }

        $group = DB::transaction(function () use ($customer, $items) {
            $unitIds = $items->where('kind', 'stay')->pluck('unit_id')->unique()->sort()->values();
            $slotIds = $items->where('kind', 'slot')->pluck('slot_id')->unique()->sort()->values();
            $units = StayUnit::whereIn('id', $unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $slots = TimeSlot::whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $group = new BookingGroup(['reference' => $this->groupReference(), 'customer_id' => $customer->id, 'total' => 0, 'payment_status' => PaymentStatus::Unpaid]);
            $group->save();
            $opts = ['customer' => $customer, 'group_id' => $group->id];

            $total = 0;
            foreach ($items as $item) {
                $booking = $item['kind'] === 'stay'
                    ? $this->bookings->reserveStay($units[$item['unit_id']], $item['check_in'], $item['check_out'], $item['rooms'], $item['guests'], $opts)
                    : $this->bookings->reserveSlot($slots[$item['slot_id']], $item['guests'], $opts);
                $total += $booking->total;
            }
            $group->forceFill(['total' => $total])->save();

            return $group;
        }, attempts: 3);

        $this->audit->log('trip.held', $group, [], ['items' => $items->count(), 'total' => $group->total]);

        return $group;
    }

    /**
     * One payment for the whole trip, then each booking moves to confirmed / pending.
     *
     * @param  array<int, array>  $detailsByBooking  booking id => details (see BookingService::checkout)
     */
    public function checkout(BookingGroup $group, array $detailsByBooking): BookingGroup
    {
        DB::transaction(function () use ($group, $detailsByBooking) {
            $group = BookingGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $bookings = Booking::where('booking_group_id', $group->id)->orderBy('starts_at')->lockForUpdate()->get();

            foreach ($bookings as $booking) {
                $this->bookings->assertHoldIsLive($booking);
                $this->bookings->applyDetails($booking, $detailsByBooking[$booking->id] ?? []);
            }

            if ($group->total > 0) {
                $charge = $this->gateway->charge($group->total, 'Excursio trip '.$group->reference);
                if ($charge['status'] !== 'succeeded') {
                    throw new BookingException('The payment did not go through. Nothing was charged; please try again.');
                }
                $this->bookings->recordPayment(null, $group->id, 'charge', $group->total, $charge);
            }

            foreach ($bookings as $booking) {
                $this->bookings->finalise($booking, $booking->total > 0);
            }
            $group->forceFill(['payment_status' => $group->total > 0 ? PaymentStatus::DemoPaid : PaymentStatus::NotRequired])->save();
        }, attempts: 3);

        $this->cart->clear();
        $this->audit->log('trip.booked', $group, [], ['total' => $group->total]);

        return $group->fresh();
    }

    private function groupReference(): string
    {
        do {
            $ref = 'EXC-TR-'.collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (BookingGroup::where('reference', $ref)->exists());

        return $ref;
    }
}
