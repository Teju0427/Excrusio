<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    protected $fillable = ['listing_id', 'path', 'alt', 'credit', 'license', 'sort_order', 'is_cover'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
