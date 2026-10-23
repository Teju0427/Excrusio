<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = ['time_slot_id', 'user_id', 'party_size'];

    protected function casts(): array
    {
        return ['offered_at' => 'datetime', 'offer_expires_at' => 'datetime'];
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
