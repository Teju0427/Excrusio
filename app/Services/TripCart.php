<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Feature F1 — one cart for a stay + dinner + activity.
 * The cart lives in the (database-backed) session; nothing is reserved until checkout.
 */
class TripCart
{
    public const MAX_ITEMS = 6;

    public const TRAVEL_BUFFER_MINUTES = 60;

    public function __construct(private Session $session, private PricingService $pricing, private AvailabilityService $availability) {}

    public function raw(): array
    {
        return $this->session->get('trip_cart', []);
    }

    public function count(): int
    {
        return count($this->raw());
    }

    public function addStay(StayUnit $unit, string $checkIn, string $checkOut, int $rooms, int $guests): void
    {
        $this->push(['kind' => 'stay', 'unit_id' => $unit->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'rooms' => $rooms, 'guests' => $guests]);
    }

    public function addSlot(TimeSlot $slot, int $guests): void
    {
        $this->push(['kind' => 'slot', 'slot_id' => $slot->id, 'guests' => $guests]);
    }

    public function remove(int $index): void
    {
        $items = $this->raw();
        unset($items[$index]);
        $this->session->put('trip_cart', array_values($items));
    }

    public function clear(): void
    {
        $this->session->forget('trip_cart');
    }

    /**
     * Items with their listing, start/end, and a live quote, sorted by start time.
     *
     * @return Collection<int, array>
     */
    public function items(): Collection
    {
        $raw = collect($this->raw());
        $units = StayUnit::whereIn('id', $raw->where('kind', 'stay')->pluck('unit_id'))->get()->keyBy('id');
        $slots = TimeSlot::whereIn('id', $raw->where('kind', 'slot')->pluck('slot_id'))->get()->keyBy('id');
        $listings = Listing::with('destination', 'stayDetail', 'diningDetail', 'activityDetail', 'coverImage')
            ->whereIn('id', $units->pluck('listing_id')->merge($slots->pluck('listing_id')))->get()->keyBy('id');

        return $raw->map(function (array $item, int $index) use ($units, $slots, $listings) {
            if ($item['kind'] === 'stay' && ($unit = $units->get($item['unit_id']))) {
                $listing = $listings[$unit->listing_id];
                $in = CarbonImmutable::parse($item['check_in']);
                $out = CarbonImmutable::parse($item['check_out']);
                $quote = $this->pricing->quoteStay($unit, $in, $out, $item['rooms']);
                $times = [
                    $in->setTimeFromTimeString($listing->stayDetail?->check_in_time ?? '14:00'),
                    $out->setTimeFromTimeString($listing->stayDetail?->check_out_time ?? '11:00'),
                ];

                return $item + ['index' => $index, 'listing' => $listing, 'unit' => $unit, 'starts' => $times[0], 'ends' => $times[1], 'total' => $quote['total'],
                    'label' => $unit->name.' · '.$item['rooms'].' '.str('room')->plural($item['rooms']).' · '.$quote['nights_count'].' '.str('night')->plural($quote['nights_count'])];
            }
            if ($item['kind'] === 'slot' && ($slot = $slots->get($item['slot_id']))) {
                $listing = $listings[$slot->listing_id];
                $quote = $this->pricing->quoteSlot($listing, $slot, $item['guests']);

                return $item + ['index' => $index, 'listing' => $listing, 'slot' => $slot, 'starts' => CarbonImmutable::instance($slot->starts_at), 'ends' => CarbonImmutable::instance($slot->ends_at), 'total' => $quote['total'],
                    'label' => $item['guests'].' '.($listing->listing_type === ListingType::Dining ? str('guest')->plural($item['guests']) : str('person')->plural($item['guests']))];
            }

            return null;   // the room type or session no longer exists
        })->filter()->sortBy(fn ($i) => $i['starts'])->values();
    }

    public function total(?Collection $items = null): int
    {
        return ($items ?? $this->items())->sum('total');
    }

    /**
     * Timing problems between items. All times are venue-local.
     *
     * @return list<string>
     */
    public function clashes(?Collection $items = null): array
    {
        $items = ($items ?? $this->items())->values();
        $problems = [];
        $stays = $items->where('kind', 'stay');
        $slots = $items->where('kind', 'slot')->values();

        foreach ($stays->values() as $i => $a) {
            foreach ($stays->values()->slice($i + 1) as $b) {
                if ($a['check_in'] < $b['check_out'] && $b['check_in'] < $a['check_out']) {
                    $problems[] = "You have two stays on the same night: {$a['listing']->title} and {$b['listing']->title}.";
                }
            }
        }

        foreach ($slots as $i => $a) {
            foreach ($slots->slice($i + 1) as $b) {
                if ($a['starts']->lessThan($b['ends']) && $b['starts']->lessThan($a['ends'])) {
                    $problems[] = "{$a['listing']->title} ({$a['starts']->format('D j M, H:i')}) overlaps with {$b['listing']->title} ({$b['starts']->format('H:i')}).";

                    continue;
                }
                if ($a['listing']->destination_id !== $b['listing']->destination_id) {
                    [$first, $second] = $a['starts']->lessThan($b['starts']) ? [$a, $b] : [$b, $a];
                    if ($first['ends']->diffInMinutes($second['starts'], false) < self::TRAVEL_BUFFER_MINUTES) {
                        $problems[] = "Allow at least an hour to travel between {$first['listing']->title} ({$first['listing']->destination->name}) and {$second['listing']->title} ({$second['listing']->destination->name}).";
                    }
                }
            }
        }

        foreach ($stays as $stay) {
            foreach ($slots as $slot) {
                if ($slot['listing']->destination_id !== $stay['listing']->destination_id) {
                    continue;
                }
                $day = $slot['starts']->toDateString();
                if ($day < $stay['check_in'] || $day > $stay['check_out']) {
                    $problems[] = "{$slot['listing']->title} on {$slot['starts']->format('D j M')} is outside your stay at {$stay['listing']->title} ({$stay['starts']->format('j M')}–{$stay['ends']->format('j M')}).";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    private function push(array $item): void
    {
        $items = $this->raw();
        if (count($items) >= self::MAX_ITEMS) {
            throw new BookingException('A trip can hold up to '.self::MAX_ITEMS.' items. Book these first, then start another.');
        }
        foreach ($items as $existing) {
            if ($existing == $item) {
                throw new BookingException('That’s already in your trip.');
            }
        }
        $items[] = $item;
        $this->session->put('trip_cart', $items);
    }
}
