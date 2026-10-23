<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['listing_id', 'cuisine', 'price_band', 'dress_code', 'max_party_size', 'deposit_per_guest', 'policies'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
