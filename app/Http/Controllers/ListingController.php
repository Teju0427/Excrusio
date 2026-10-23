<?php

namespace App\Http\Controllers;

use App\Enums\ListingType;
use App\Models\Amenity;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Review;
use App\Services\ListingSearchService;
use App\Services\WeatherService;
use App\Support\SearchFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(Request $request, string $segment, ListingSearchService $search): View
    {
        $type = ListingType::fromSegment($segment);
        [$filters, $filterErrors] = SearchFilters::from($request);

        return view('listings.index', [
            'type' => $type,
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'listings' => $search->search($type, $filters),
            'categories' => Category::where('listing_type', $type->value)->where('is_active', true)->orderBy('sort_order')->get(),
            'amenities' => Amenity::where('applies_to', $type->value)->orderBy('name')->get(),
            'cuisines' => $type === ListingType::Dining
                ? Listing::published()->ofType($type)->join('dining_details', 'dining_details.listing_id', '=', 'listings.id')
                    ->distinct()->orderBy('cuisine')->pluck('cuisine') : collect(),
            // The rating filter only appears once real, approved reviews exist.
            'hasReviews' => Review::where('status', 'approved')->exists(),
            'savedIds' => $request->user()?->isCustomer() ? $request->user()->wishlist()->pluck('listings.id')->all() : [],
        ]);
    }

    public function show(Request $request, string $segment, Listing $listing, WeatherService $weather): View
    {
        $type = ListingType::fromSegment($segment);
        // Wrong category in the URL, or a draft the visitor isn't allowed to preview → 404.
        abort_unless($listing->listing_type === $type && Gate::allows('view', $listing), 404);

        $listing->load([
            'images', 'destination', 'category', 'amenities', 'provider:id,name',
            'stayDetail', 'diningDetail', 'activityDetail',
            'stayUnits' => fn ($q) => $q->where('is_active', true)->orderBy('price_per_night'),
        ]);

        $forecast = null;
        if ($type === ListingType::Activity && $listing->activityDetail?->weather_dependent && $listing->latitude) {
            $forecast = $weather->forecast($listing->latitude, $listing->longitude, $listing->destination->timezone);
        }

        $reviews = $listing->reviews()->approved()->with('customer:id,name', 'booking:id,starts_at,listing_type')
            ->latest()->paginate(6, ['*'], 'reviews')->withQueryString()->fragment('reviews');
        $distribution = $listing->reviews()->approved()->selectRaw('rating, count(*) n')->groupBy('rating')->pluck('n', 'rating');

        return view('listings.show', [
            'listing' => $listing,
            'reviews' => $reviews,
            'distribution' => $distribution,
            'type' => $type,
            'forecast' => $forecast ? array_slice($forecast, 0, 7) : null,
            'saved' => $request->user()?->isCustomer() && $request->user()->wishlist()->whereKey($listing->id)->exists(),
            'today' => now($listing->destination->timezone)->toDateString(),
            'maxDate' => now($listing->destination->timezone)->addDays(59)->toDateString(),
        ]);
    }
}
