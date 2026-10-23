<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['rating', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderated_at' => 'datetime', 'host_replied_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** "Priya S." — never the full surname or email in public. */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->customer?->name)) ?: ['Guest'];
        $first = $parts[0] ?: 'Guest';

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }
}
