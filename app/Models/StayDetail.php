<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StayDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['listing_id', 'check_in_time', 'check_out_time', 'house_rules', 'min_nights', 'max_nights'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
