<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StayUnit extends Model
{
    protected $fillable = [
        'listing_id', 'name', 'description', 'bed_configuration', 'max_guests',
        'quantity', 'price_per_night', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }
}
