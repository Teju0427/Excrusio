<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Models\ActivityDetail;
use App\Models\Destination;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the browse/search query for each category. Date filters only return
 * listings that still have room (stays) or a free slot (dining/activities).
 */
class ListingSearchService
{
    public const PER_PAGE = 12;

    public function __construct(private AvailabilityService $availability) {}

    public function search(ListingType $type, array $f): LengthAwarePaginator
    {
        $query = Listing::published()->ofType($type)->forCards();

        $this->applyText($query, $f['q'] ?? null);

        if (! empty($f['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $f['category']));
        }
        foreach ($f['amenities'] ?? [] as $slug) {   // every chosen amenity must be present
            $query->whereHas('amenities', fn ($q) => $q->where('slug', $slug));
        }
        if (! empty($f['rating'])) {
            $query->where('reviews_count', '>', 0)->where('rating_avg', '>=', $f['rating']);
        }

        match ($type) {
            ListingType::Stay => $this->applyStay($query, $f),
            ListingType::Dining => $this->applyDining($query, $f),
            ListingType::Activity => $this->applyActivity($query, $f),
        };

        $this->applySort($query, $type, $f['sort'] ?? 'recommended');

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /** Destination (incl. aliases like "Nihon" or "Kodagu") or listing name. */
    public function applyText(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }
        $destinationIds = Destination::matching($term)->pluck('id');
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn ($q) => $q->whereIn('destination_id', $destinationIds)->orWhere('title', 'like', $like));
    }

    private function applyStay(Builder $query, array $f): void
    {
        $guests = (int) ($f['guests'] ?? 1);
        $rooms = (int) ($f['rooms'] ?? 1);
        [$min, $max] = $this->priceRange($f);

        // A room type qualifies if `rooms` of it can hold the group and it fits the price range (per night).
        $unitFits = function ($q) use ($guests, $rooms, $min, $max) {
            $q->where('is_active', true)->where('quantity', '>=', $rooms)
                ->whereRaw('max_guests * ? >= ?', [$rooms, $guests]);
            if ($min !== null) {
                $q->where('price_per_night', '>=', $min);
            }
            if ($max !== null) {
                $q->where('price_per_night', '<=', $max);
            }
        };
        $query->whereHas('stayUnits', $unitFits);

        if (! empty($f['check_in']) && ! empty($f['check_out'])) {
            $in = CarbonImmutable::parse($f['check_in']);
            $out = CarbonImmutable::parse($f['check_out']);
            $candidateIds = (clone $query)->reorder()->pluck('listings.id');
            $units = StayUnit::whereIn('listing_id', $candidateIds)->where($unitFits)->get();
            $left = $this->availability->roomsLeftForUnits($units, $in, $out);

            $availableListingIds = $units->filter(fn ($u) => $left[$u->id] >= $rooms)->pluck('listing_id')->unique();
            $query->whereIn('listings.id', $availableListingIds);
        }
    }

    private function applyDining(Builder $query, array $f): void
    {
        $this->applyBasePrice($query, $f);
        if (! empty($f['cuisine'])) {
            $query->whereHas('diningDetail', fn ($q) => $q->where('cuisine', 'like', '%'.$f['cuisine'].'%'));
        }
        if (! empty($f['price_band'])) {
            $query->whereHas('diningDetail', fn ($q) => $q->where('price_band', $f['price_band']));
        }
        if (! empty($f['guests'])) {
            $query->whereHas('diningDetail', fn ($q) => $q->where('max_party_size', '>=', $f['guests']));
        }
        $this->applySlotAvailability($query, $f);
    }

    private function applyActivity(Builder $query, array $f): void
    {
        $this->applyBasePrice($query, $f);
        if (! empty($f['difficulty'])) {
            $query->whereHas('activityDetail', fn ($q) => $q->where('difficulty', $f['difficulty']));
        }
        if (! empty($f['duration'])) {
            $query->whereHas('activityDetail', fn ($q) => match ($f['duration']) {
                'short' => $q->where('duration_minutes', '<=', 120),
                'half' => $q->whereBetween('duration_minutes', [121, 300]),
                'full' => $q->where('duration_minutes', '>', 300),
            });
        }
        $this->applySlotAvailability($query, $f);
    }

    /** With a date: keep only listings with an open slot that day with enough places left. */
    private function applySlotAvailability(Builder $query, array $f): void
    {
        $guests = (int) ($f['guests'] ?? 1);

        if (empty($f['date'])) {
            if (! empty($f['guests'])) {
                $query->whereHas('timeSlots', fn ($q) => $q->where('status', 'open')->where('capacity', '>=', $guests));
            }

            return;
        }

        [$from, $to] = AvailabilityService::timeWindow($f['time'] ?? null);
        $candidates = (clone $query)->reorder()->with('destination', 'activityDetail')->get(['listings.*']);
        $ids = [];

        foreach ($candidates as $listing) {
            $slots = $this->availability->slotsOn($listing, $f['date'], $f['time'] ?? null);
            if ($slots->contains(fn (TimeSlot $s) => $s->remaining >= $guests)) {
                $ids[] = $listing->id;
            }
        }
        $query->whereIn('listings.id', $ids);
    }

    private function applyBasePrice(Builder $query, array $f): void
    {
        [$min, $max] = $this->priceRange($f);
        if ($min !== null) {
            $query->where('base_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('base_price', '<=', $max);
        }
    }

    /** Filters arrive in rupees; the database stores paise. */
    private function priceRange(array $f): array
    {
        return [
            isset($f['min_price']) ? (int) $f['min_price'] * 100 : null,
            isset($f['max_price']) ? (int) $f['max_price'] * 100 : null,
        ];
    }

    private function applySort(Builder $query, ListingType $type, string $sort): void
    {
        $priceColumn = $type === ListingType::Stay ? 'min_unit_price' : 'base_price';

        match ($sort) {
            'price_asc' => $query->orderBy($priceColumn)->orderBy('listings.id'),
            'price_desc' => $query->orderByDesc($priceColumn)->orderBy('listings.id'),
            'newest' => $query->latest('published_at'),
            'rating' => $query->orderByDesc('reviews_count')->orderByDesc('rating_avg')->orderBy('listings.id'),
            'duration' => $type === ListingType::Activity
                ? $query->orderBy(ActivityDetail::select('duration_minutes')->whereColumn('activity_details.listing_id', 'listings.id'))
                : $query->orderBy('listings.id'),
            default => $query->orderByDesc('is_featured')->orderByDesc('reviews_count')->orderBy('listings.id'),
        };
    }
}
