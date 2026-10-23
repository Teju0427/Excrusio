<?php

namespace App\Http\Controllers;

use App\Enums\ListingType;
use App\Models\Listing;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * JSON endpoints used by the listing pages (Fetch API) to show live availability and prices.
 * Read-only: nothing is reserved until the booking step (Phase 5).
 */
class AvailabilityController extends Controller
{
    public function stay(Request $request, Listing $listing, AvailabilityService $availability, PricingService $pricing): JsonResponse
    {
        abort_unless($listing->listing_type === ListingType::Stay && Gate::allows('view', $listing), 404);
        $listing->loadMissing('stayDetail', 'destination');
        $today = now($listing->destination->timezone)->toDateString();

        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:10'],
        ], [
            'check_in.after_or_equal' => 'Check-in can’t be in the past.',
            'check_out.after' => 'Check-out must be after check-in.',
        ]);

        $in = CarbonImmutable::parse($data['check_in']);
        $out = CarbonImmutable::parse($data['check_out']);
        $nights = $in->diffInDays($out);
        $rooms = (int) ($data['rooms'] ?? 1);
        $detail = $listing->stayDetail;

        if ($detail && ($nights < $detail->min_nights || $nights > $detail->max_nights)) {
            return response()->json(['message' => "This stay takes bookings of {$detail->min_nights}–{$detail->max_nights} nights.", 'errors' => ['check_out' => ['Invalid length of stay.']]], 422);
        }

        $units = $listing->stayUnits()->where('is_active', true)->orderBy('price_per_night')->get();
        $left = $availability->roomsLeftForUnits($units, $in, $out);

        return response()->json([
            'nights' => $nights,
            'units' => $units->map(function ($unit) use ($left, $rooms, $data, $pricing, $in, $out) {
                $fitsGuests = $unit->max_guests * $rooms >= $data['guests'];
                $available = $left[$unit->id] >= $rooms && $fitsGuests;
                $quote = $available ? $pricing->quoteStay($unit, $in, $out, $rooms) : null;

                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'beds' => $unit->bed_configuration,
                    'max_guests' => $unit->max_guests,
                    'rooms_left' => $left[$unit->id],
                    'available' => $available,
                    'reason' => $available ? null : (! $fitsGuests ? "Sleeps {$unit->max_guests} per room" : 'Sold out for these dates'),
                    'quote' => $quote ? $this->formatQuote($quote) + [
                        'nights' => array_map(fn ($n) => ['date' => CarbonImmutable::parse($n['date'])->format('D j M'), 'price' => money($n['price']), 'label' => $n['label']], $quote['nights']),
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function slots(Request $request, Listing $listing, AvailabilityService $availability, PricingService $pricing, WeatherService $weather): JsonResponse
    {
        abort_unless($listing->listing_type !== ListingType::Stay && Gate::allows('view', $listing), 404);
        $listing->loadMissing('destination', 'activityDetail', 'diningDetail');
        $today = now($listing->destination->timezone);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before:'.$today->copy()->addDays(60)->toDateString()],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'date.after_or_equal' => 'Please choose today or a future date.',
            'date.before' => 'Reservations open up to 60 days ahead.',
        ]);

        $guests = (int) $data['guests'];
        $maxParty = $listing->diningDetail?->max_party_size;
        if ($maxParty && $guests > $maxParty) {
            return response()->json(['message' => "Online reservations are for parties of up to {$maxParty}.", 'errors' => ['guests' => ['Party too large.']]], 422);
        }

        $slots = $availability->slotsOn($listing, $data['date']);
        $forecast = ($listing->activityDetail?->weather_dependent && $listing->latitude)
            ? $weather->forDate($listing->latitude, $listing->longitude, $listing->destination->timezone, $data['date']) : null;

        return response()->json([
            'date' => $data['date'],
            'weather' => $forecast,
            'slots' => $slots->map(fn ($slot) => [
                'id' => $slot->id,
                'time' => $slot->starts_at->format('H:i'),
                'ends' => $slot->ends_at->format('H:i'),
                'remaining' => $slot->remaining,
                'available' => $slot->remaining >= $guests,
                'quote' => $this->formatQuote($pricing->quoteSlot($listing, $slot, $guests)),
            ])->values(),
        ]);
    }

    private function formatQuote(array $q): array
    {
        return [
            'unit_price' => money($q['unit_price']),
            'subtotal' => money($q['subtotal']),
            'fees' => $q['fees'] ? money($q['fees']) : null,
            'total' => money($q['total']),
            'total_paise' => $q['total'],
        ];
    }
}
