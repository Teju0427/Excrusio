<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['booking_id', 'booking_group_id', 'gateway', 'gateway_reference', 'type', 'amount', 'status', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
