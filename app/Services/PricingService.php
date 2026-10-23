<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\PriceRule;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Every price shown or charged is calculated here, on the server, in paise.
 * Prices sent by a browser are never trusted.
 */
class PricingService
{
    /**
     * @return array{nights: list<array{date: string, price: int, label: ?string}>, nights_count: int, rooms: int,
     *               unit_price: int, subtotal: int, fees: int, total: int}
     */
    public function quoteStay(StayUnit $unit, CarbonInterface $checkIn, CarbonInterface $checkOut, int $rooms = 1): array
    {
        $rules = PriceRule::where('listing_id', $unit->listing_id)
            ->where(fn ($q) => $q->whereNull('stay_unit_id')->orWhere('stay_unit_id', $unit->id))
            ->where('start_date', '<', $checkOut->toDateString())->where('end_date', '>=', $checkIn->toDateString())
            ->orderByDesc('id')->get();

        $nights = [];
        foreach (CarbonPeriod::create($checkIn->toDateString(), CarbonImmutable::parse($checkOut->toDateString())->subDay()->toDateString()) as $night) {
            // Most recently added matching rule wins; unit-specific rules beat listing-wide ones.
            $rule = $rules->sortByDesc(fn ($r) => [$r->stay_unit_id ? 1 : 0, $r->id])
                ->first(fn (PriceRule $r) => $night->between($r->start_date, $r->end_date) && ($r->weekday_mask & (1 << $night->dayOfWeek)));
            $nights[] = ['date' => $night->toDateString(), 'price' => $rule?->price ?? $unit->price_per_night, 'label' => $rule?->label];
        }

        $subtotal = array_sum(array_column($nights, 'price')) * $rooms;

        return [
            'nights' => $nights,
            'nights_count' => count($nights),
            'rooms' => $rooms,
            'unit_price' => count($nights) ? intdiv(array_sum(array_column($nights, 'price')), count($nights)) : 0,
            'subtotal' => $subtotal,
            'fees' => $fees = $this->fees($subtotal),
            'total' => $subtotal + $fees,
        ];
    }

    /**
     * Activities: price per participant. Dining: optional deposit per guest (often ₹0 = pay at the restaurant).
     *
     * @return array{unit_price: int, guests: int, subtotal: int, fees: int, total: int}
     */
    public function quoteSlot(Listing $listing, TimeSlot $slot, int $guests): array
    {
        $unit = match ($listing->listing_type) {
            ListingType::Dining => (int) ($listing->diningDetail?->deposit_per_guest ?? 0),
            default => (int) ($slot->price ?? $listing->base_price),
        };
        $subtotal = $unit * $guests;

        return [
            'unit_price' => $unit,
            'guests' => $guests,
            'subtotal' => $subtotal,
            'fees' => $fees = $this->fees($subtotal),
            'total' => $subtotal + $fees,
        ];
    }

    private function fees(int $subtotal): int
    {
        return (int) round($subtotal * config('excursio.service_fee_percent', 0) / 100);
    }
}
