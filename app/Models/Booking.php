<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\ListingType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    // Status, prices and payment fields are set only by services, never mass-assigned from forms.
    protected $fillable = [
        'guest_name', 'guest_email', 'guest_phone', 'special_requests',
    ];

    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'cancellation_policy' => CancellationPolicy::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'response_due_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'eligibility_acknowledged_at' => 'datetime',
            'price_breakdown' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function stayUnit(): BelongsTo
    {
        return $this->belongsTo(StayUnit::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(BookingGroup::class, 'booking_group_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Bookings that currently hold inventory: pending/confirmed/completed,
     * plus unpaid holds that have not expired yet.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', BookingStatus::occupying())
                ->orWhere(fn (Builder $h) => $h->where('status', BookingStatus::AwaitingPayment->value)
                    ->where('hold_expires_at', '>', now()));
        });
    }
}
