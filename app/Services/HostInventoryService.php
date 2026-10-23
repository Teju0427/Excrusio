<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Schedule;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\WaitlistEntry;
use App\Support\VenueClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Host-side availability changes. The same row locks as the booking engine are taken,
 * so a host edit and a guest booking can never interleave and oversell.
 * Existing bookings are never silently changed: prices are snapshotted on each booking,
 * and a change that would strand a booking is refused with a clear message.
 */
class HostInventoryService
{
    public function __construct(private SlotGenerator $slots, private AuditLogger $audit) {}

    // ------------------------------------------------------------------ stays

    public function updateUnit(StayUnit $unit, array $data): StayUnit
    {
        $old = $unit->only(['name', 'max_guests', 'quantity', 'price_per_night', 'is_active']);

        $unit = DB::transaction(function () use ($unit, $data) {
            $unit = StayUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $peak = $this->peakRoomsBooked($unit);
            if ($data['quantity'] < $peak['rooms']) {
                throw new BookingException("You can’t go below {$peak['rooms']} — that many rooms of this type are already booked on {$peak['date']}.");
            }
            $unit->fill($data)->save();

            return $unit;
        }, attempts: 3);

        $this->audit->log('stay_unit.updated', $unit, $old, $unit->only(array_keys($old)));

        return $unit;
    }

    public function addUnit(Listing $listing, array $data): StayUnit
    {
        $unit = $listing->stayUnits()->create($data + ['is_active' => true]);
        $this->audit->log('stay_unit.created', $unit, [], $data);

        return $unit;
    }

    /**
     * Close dates (stop new sales). Existing bookings in the period stay valid; the
     * returned count tells the host how many they still have to honour.
     */
    public function addBlock(Listing $listing, array $data): array
    {
        [$block, $affected] = DB::transaction(function () use ($listing, $data) {
            // Lock the affected room types so no booking slips in while we save.
            $units = StayUnit::where('listing_id', $listing->id)
                ->when($data['stay_unit_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
                ->orderBy('id')->lockForUpdate()->get();
            if ($units->isEmpty()) {
                throw new BookingException('Choose one of your room types.');
            }
            if (isset($data['rooms']) && $data['rooms'] > $units->max('quantity')) {
                throw new BookingException('That’s more rooms than this room type has.');
            }
            $affected = Booking::occupying()->whereIn('stay_unit_id', $units->pluck('id'))
                ->where('check_in', '<', $data['end_date'])->where('check_out', '>', $data['start_date'])->count();

            return [$listing->availabilityBlocks()->create($data), $affected];
        }, attempts: 3);

        $this->audit->log('availability.blocked', $block, [], $data);

        return [$block, $affected];
    }

    public function removeBlock(AvailabilityBlock $block): void
    {
        $this->audit->log('availability.unblocked', $block, $block->only(['start_date', 'end_date', 'rooms', 'stay_unit_id']), []);
        $block->delete();
    }

    /** Highest number of rooms of this type booked on any night from today on. */
    public function peakRoomsBooked(StayUnit $unit): array
    {
        $bookings = Booking::occupying()->where('stay_unit_id', $unit->id)
            ->where('check_out', '>', now()->toDateString())->get(['check_in', 'check_out', 'rooms']);
        $peak = ['rooms' => 0, 'date' => null];
        if ($bookings->isEmpty()) {
            return $peak;
        }
        $from = CarbonImmutable::today();
        $to = CarbonImmutable::parse($bookings->max('check_out'))->subDay();
        foreach (CarbonPeriod::create($from, $to) as $night) {
            $d = $night->toDateString();
            $rooms = $bookings->filter(fn ($b) => $b->check_in->toDateString() <= $d && $b->check_out->toDateString() > $d)->sum('rooms');
            if ($rooms > $peak['rooms']) {
                $peak = ['rooms' => $rooms, 'date' => $night->format('j M Y')];
            }
        }

        return $peak;
    }

    // ------------------------------------------------- dining & activities

    /**
     * Replace the weekly schedule and rebuild future sessions. Sessions that already
     * have bookings or a waitlist are kept exactly as they are.
     *
     * @return array{removed:int, kept:int, created:int}
     */
    public function saveSchedules(Listing $listing, array $rows): array
    {
        $result = DB::transaction(function () use ($listing, $rows) {
            Listing::whereKey($listing->id)->lockForUpdate()->first();
            $listing->schedules()->delete();
            foreach ($rows as $row) {
                $listing->schedules()->create($row);
            }

            $future = TimeSlot::where('listing_id', $listing->id)
                ->where('starts_at', '>', VenueClock::now($listing)->format('Y-m-d H:i:s'));
            // Lock the future sessions first: a guest booking one of them waits until we finish.
            $ids = (clone $future)->orderBy('id')->lockForUpdate()->pluck('id');
            $inUse = Booking::whereIn('time_slot_id', $ids)->distinct()->pluck('time_slot_id')
                ->merge(WaitlistEntry::whereIn('time_slot_id', $ids)->distinct()->pluck('time_slot_id'))
                ->unique();
            $removed = TimeSlot::whereIn('id', $ids->diff($inUse))->delete();

            return ['removed' => $removed, 'kept' => $inUse->count()];
        }, attempts: 3);

        $listing->unsetRelation('schedules');
        $result['created'] = $this->slots->generate($listing);
        $this->audit->log('schedule.updated', $listing, [], ['rules' => count($rows)] + $result);

        return $result;
    }

    /** Close a session to new bookings (existing guests keep their places) or reopen it. */
    public function setSlotStatus(TimeSlot $slot, string $status): void
    {
        DB::transaction(function () use ($slot, $status) {
            $slot = TimeSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($slot->status === 'cancelled') {
                throw new BookingException('This session was cancelled and can’t be reopened.');
            }
            $slot->forceFill(['status' => $status])->save();
        });
        $this->audit->log('slot.'.($status === 'closed' ? 'closed' : 'reopened'), $slot, [], []);
    }
}
