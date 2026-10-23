<?php

namespace App\Http\Controllers;

use App\Enums\ListingType;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Curated order: featured first, then best-reviewed, then the order listings were added.
        // (Not by timestamp, which made the order depend on how fast the seeder ran.)
        // Only plain listing IDs are cached (Laravel refuses to unserialize objects from the
        // cache for security). ListingObserver clears this key whenever a listing changes.
        $ids = Cache::remember('home:rows', now()->addMinutes(10), fn () => collect(ListingType::cases())
            ->mapWithKeys(fn (ListingType $type) => [$type->value => Listing::published()->ofType($type)
                ->orderByDesc('is_featured')->orderByDesc('reviews_count')->orderBy('id')->limit(4)->pluck('id')->all()])
            ->all());

        $listings = Listing::forCards()->whereIn('id', array_merge(...array_values($ids)))->get()->keyBy('id');
        $row = fn (string $type): Collection => collect($ids[$type] ?? [])->map(fn ($id) => $listings->get($id))->filter()->values();

        return view('pages.home', [
            'stays' => $row(ListingType::Stay->value),
            'dining' => $row(ListingType::Dining->value),
            'activities' => $row(ListingType::Activity->value),
            'destinations' => Destination::where('is_featured', true)->orderBy('sort_order')->limit(5)
                ->withCount([
                    'listings' => fn ($q) => $q->published(),
                    'listings as stays_count' => fn ($q) => $q->published()->ofType(ListingType::Stay),
                    'listings as dining_count' => fn ($q) => $q->published()->ofType(ListingType::Dining),
                    'listings as activities_count' => fn ($q) => $q->published()->ofType(ListingType::Activity),
                ])->get(),
            'activityCategories' => Category::where('listing_type', ListingType::Activity->value)
                ->where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']),
            'destinationNames' => Destination::orderBy('name')->pluck('name'),
            'savedIds' => request()->user()?->isCustomer() ? request()->user()->wishlist()->pluck('listings.id')->all() : null,
            'today' => now('Asia/Kolkata')->toDateString(),
            'tomorrow' => now('Asia/Kolkata')->addDay()->toDateString(),
        ]);
    }
}
