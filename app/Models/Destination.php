<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    protected $fillable = [
        'name', 'slug', 'local_name', 'country', 'region', 'timezone', 'summary',
        'hero_image', 'latitude', 'longitude', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(DestinationAlias::class);
    }

    /**
     * Feature F6: match by English name, local name, country or any alias
     * ("Nihon" → Japan destinations, "Bangalore" → Bengaluru).
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('name', 'like', $like)
                ->orWhere('local_name', 'like', $like)
                ->orWhere('country', 'like', $like)
                ->orWhere('region', 'like', $like)
                ->orWhereHas('aliases', fn (Builder $a) => $a->where('alias', $term)->orWhere('alias', 'like', $like));
        });
    }
}
