<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Answers "what is still free?" for rooms and time slots.
 * The same rules are reused by the booking engine (inside a locked transaction) in Phase 5.
 */
class AvailabilityService
{
    public function __construct(private SlotGenerator $slots) {}

    /** Rooms of this type still free for every night from check-in up to (not including) check-out. */
    public function roomsLeft(StayUnit $unit, CarbonInterface $checkIn, CarbonInterface $checkOut): int
    {
        return $this->roomsLeftForUnits(collect([$unit]), $checkIn, $checkOut)[$unit->id] ?? 0;
    }

    /**
     * Batch version (2 queries for any number of room types).
     *
     * @return array<int, int> unit id => rooms left
     */
    public function roomsLeftForUnits(Collection $units, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        if ($units->isEmpty()) {
            return [];
        }
        $in = $checkIn->toDateString();
        $out = $checkOut->toDateString();

        // Overlap rule: an existing stay overlaps when it starts before our check-out and ends after our check-in.
        $bookings = Booking::occupying()->whereIn('stay_unit_id', $units->pluck('id'))
            ->where('check_in', '<', $out)->where('check_out', '>', $in)
            ->get(['stay_unit_id', 'check_in', 'check_out', 'rooms'])->groupBy('stay_unit_id');

        $blocks = AvailabilityBlock::whereIn('listing_id', $units->pluck('listing_id')->unique())
            ->where('start_date', '<', $out)->where('end_date', '>', $in)->get();

        $nights = CarbonPeriod::create($in, CarbonImmutable::parse($out)->subDay()->toDateString());
        $result = [];

        foreach ($units as $unit) {
            $maxUsed = 0;
            foreach ($nights as $night) {
                $date = $night->toDateString();
                $used = ($bookings[$unit->id] ?? collect())
                    ->filter(fn ($b) => $b->check_in->toDateString() <= $date && $b->check_out->toDateString() > $date)
                    ->sum('rooms');
                foreach ($blocks as $block) {
                    $appliesToUnit = $block->stay_unit_id === null ? $block->listing_id === $unit->listing_id : $block->stay_unit_id === $unit->id;
                    if ($appliesToUnit && $block->start_date->toDateString() <= $date && $block->end_date->toDateString() > $date) {
                        $used += $block->rooms ?? $unit->quantity;   // null = whole room type closed
                    }
                }
                $maxUsed = max($maxUsed, $used);
            }
            $result[$unit->id] = max(0, $unit->quantity - $maxUsed);
        }

        return $result;
    }

    /**
     * Seats / places still free per slot.
     *
     * @return array<int, int> slot id => remaining
     */
    public function remainingForSlots(Collection $slots, ?int $forUserId = null): array
    {
        if ($slots->isEmpty()) {
            return [];
        }
        $taken = Booking::occupying()->whereIn('time_slot_id', $slots->pluck('id'))
            ->groupBy('time_slot_id')->selectRaw('time_slot_id, SUM(guests) as taken')->pluck('taken', 'time_slot_id');

        // Seats offered to someone on the waitlist are held for them for 30 minutes (feature F2).
        $held = WaitlistEntry::whereIn('time_slot_id', $slots->pluck('id'))
            ->where('status', 'offered')->where('offer_expires_at', '>', now())
            ->when($forUserId, fn ($q) => $q->where('user_id', '!=', $forUserId))
            ->groupBy('time_slot_id')->selectRaw('time_slot_id, SUM(party_size) as held')->pluck('held', 'time_slot_id');

        return $slots->mapWithKeys(fn (TimeSlot $s) => [
            $s->id => max(0, $s->capacity - (int) ($taken[$s->id] ?? 0) - (int) ($held[$s->id] ?? 0)),
        ])->all();
    }

    public function slotRemaining(TimeSlot $slot, ?int $forUserId = null): int
    {
        return $this->remainingForSlots(collect([$slot]), $forUserId)[$slot->id];
    }

    /**
     * Open, still-bookable slots on a date (venue local time), each with a `remaining` attribute.
     * Slots inside the booking cut-off (e.g. ski lessons 18 h before) are excluded.
     */
    public function slotsOn(Listing $listing, string $date, ?string $window = null): Collection
    {
        $this->ensureSlots($listing);
        $listing->loadMissing('destination', 'activityDetail');

        $earliest = CarbonImmutable::now($listing->destination->timezone)->addHours($this->cutoffHours($listing));
        [$from, $to] = self::timeWindow($window);

        $slots = $listing->timeSlots()->where('status', 'open')
            ->whereBetween('starts_at', [$date.' '.$from, $date.' '.$to])
            ->where('starts_at', '>=', $earliest->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')->get();

        $remaining = $this->remainingForSlots($slots);

        return $slots->each(fn (TimeSlot $s) => $s->setAttribute('remaining', $remaining[$s->id]));
    }

    public function cutoffHours(Listing $listing): int
    {
        return match ($listing->listing_type) {
            ListingType::Activity => $listing->activityDetail?->booking_cutoff_hours ?? 12,
            ListingType::Dining => 2,
            default => 0,
        };
    }

    /** Free hosting has no scheduler, so slots are topped up lazily whenever someone looks. */
    public function ensureSlots(Listing $listing): void
    {
        if ($listing->listing_type === ListingType::Stay) {
            return;
        }
        $horizon = CarbonImmutable::now()->addDays(SlotGenerator::DAYS_AHEAD - 15)->toDateString();
        if (! $listing->timeSlots()->where('starts_at', '>=', $horizon)->exists() && $listing->schedules()->exists()) {
            $this->slots->generate($listing);
        }
    }

    /** @return array{0: string, 1: string} */
    public static function timeWindow(?string $window): array
    {
        return match ($window) {
            'breakfast' => ['07:00:00', '11:00:00'],
            'lunch' => ['11:30:00', '15:30:00'],
            'dinner' => ['18:00:00', '23:59:59'],
            'morning' => ['00:00:00', '11:59:59'],
            'afternoon' => ['12:00:00', '23:59:59'],
            default => ['00:00:00', '23:59:59'],
        };
    }
}
