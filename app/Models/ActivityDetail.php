<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['listing_id', 'duration_minutes', 'difficulty', 'min_age', 'inclusions', 'equipment', 'safety_info', 'weather_dependent', 'booking_cutoff_hours'];

    protected function casts(): array
    {
        return ['weather_dependent' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
