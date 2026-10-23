<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // role and status are NOT fillable, so a signup form can never make itself an admin.
    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    /** Defaults that match the database, so new (unsaved or not-yet-refreshed) models have them too. */
    protected $attributes = ['role' => 'customer', 'status' => 'active', 'is_demo' => false, 'phone' => null];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'is_demo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'wishlists')->withTimestamps();
    }
}
