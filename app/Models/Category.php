<?php

namespace App\Models;

use App\Enums\ListingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['listing_type', 'name', 'slug', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['listing_type' => ListingType::class, 'is_active' => 'boolean'];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
