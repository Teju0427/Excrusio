<?php

namespace Tests\Concerns;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Test helper: inserts bookings directly (the real booking engine arrives in Phase 5). */
trait CreatesBookings
{
    protected function bookStay(StayUnit $unit, string $in, string $out, int $rooms = 1, BookingStatus $status = BookingStatus::Confirmed, array $extra = []): Booking
    {
        $listing = Listing::findOrFail($unit->listing_id);

        return $this->makeBooking($listing, array_merge([
            'stay_unit_id' => $unit->id, 'check_in' => $in, 'check_out' => $out, 'rooms' => $rooms,
            'nights' => CarbonImmutable::parse($in)->diffInDays($out),
            'starts_at' => $in.' 14:00:00', 'ends_at' => $out.' 11:00:00', 'guests' => $rooms, 'status' => $status,
        ], $extra));
    }

    protected function bookSlot(TimeSlot $slot, int $guests, BookingStatus $status = BookingStatus::Confirmed, array $extra = []): Booking
    {
        return $this->makeBooking(Listing::findOrFail($slot->listing_id), array_merge([
            'time_slot_id' => $slot->id, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at,
            'guests' => $guests, 'status' => $status,
        ], $extra));
    }

    private function makeBooking(Listing $listing, array $attrs): Booking
    {
        $customer = User::factory()->customer()->create();
        $booking = new Booking(['guest_name' => $customer->name]);
        $booking->forceFill(array_merge([
            'reference' => 'EXC-T-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id, 'listing_id' => $listing->id, 'listing_type' => $listing->listing_type,
            'unit_price' => 100000, 'subtotal' => 100000, 'total' => 100000,
            'cancellation_policy' => $listing->cancellation_policy, 'payment_status' => PaymentStatus::DemoPaid,
        ], $attrs))->save();

        return $booking;
    }
}
