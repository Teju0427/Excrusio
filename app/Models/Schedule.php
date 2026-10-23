<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = ['listing_id', 'weekday', 'start_time', 'end_time', 'interval_minutes', 'slot_length_minutes', 'capacity'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
