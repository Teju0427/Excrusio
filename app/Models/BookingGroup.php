<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingGroup extends Model
{
    protected $fillable = ['reference', 'customer_id', 'total', 'payment_status'];

    protected function casts(): array
    {
        return ['payment_status' => PaymentStatus::class];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
