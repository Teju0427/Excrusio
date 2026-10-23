<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingParticipant extends Model
{
    protected $fillable = ['booking_id', 'full_name', 'age'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
