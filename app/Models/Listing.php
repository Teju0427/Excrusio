<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\CancellationPolicy;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Observers\ListingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ListingObserver::class)]
class Listing extends Model
{
    protected $fillable = [
        'destination_id', 'category_id', 'listing_type', 'title', 'slug', 'summary',
        'description', 'address', 'latitude', 'longitude', 'base_price', 'booking_mode',
        'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'status' => ListingStatus::class,
            'booking_mode' => BookingMode::class,
            'cancellation_policy' => CancellationPolicy::class,
            'is_featured' => 'boolean',
            'is_illustrative' => 'boolean',
            'rating_avg' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---- Relationships ---------------------------------------------------

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->where('is_cover', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function stayDetail(): HasOne
    {
        return $this->hasOne(StayDetail::class);
    }

    public function diningDetail(): HasOne
    {
        return $this->hasOne(DiningDetail::class);
    }

    public function activityDetail(): HasOne
    {
        return $this->hasOne(ActivityDetail::class);
    }

    public function stayUnits(): HasMany
    {
        return $this->hasMany(StayUnit::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published->value);
    }

    public function scopeOfType(Builder $query, ListingType $type): Builder
    {
        return $query->where('listing_type', $type->value);
    }

    /** Columns and relations every listing card needs (avoids N+1 queries). */
    public function scopeForCards(Builder $query): Builder
    {
        return $query->with([
            'coverImage', 'destination:id,name,slug,local_name,country',
            'category:id,name', 'stayDetail', 'diningDetail', 'activityDetail',
        ])->withMin(['stayUnits as min_unit_price' => fn ($q) => $q->where('is_active', true)], 'price_per_night')
            ->withMax(['stayUnits as max_unit_guests' => fn ($q) => $q->where('is_active', true)], 'max_guests');
    }

    // ---- Helpers ---------------------------------------------------------

    /** Lowest advertised price ("from ₹…"), in paise. */
    public function fromPrice(): int
    {
        $min = $this->getAttributes()['min_unit_price'] ?? null;   // set by scopeForCards()
        if ($this->listing_type === ListingType::Stay && $min) {
            return (int) $min;
        }

        return (int) $this->base_price;
    }

    public function priceUnitLabel(): string
    {
        return match ($this->listing_type) {
            ListingType::Stay => 'per night',
            ListingType::Dining => 'avg. per guest',
            ListingType::Activity => 'per person',
        };
    }

    public function url(): string
    {
        return route('listings.show', [$this->listing_type->routeSegment(), $this->slug]);
    }
}
