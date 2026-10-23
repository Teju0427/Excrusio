<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** The guest who booked, the host who owns the listing, or an admin. */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($booking->customer_id !== null && $booking->customer_id === $user->id) {
            return true;
        }

        return $user->isProvider() && $booking->listing()->where('provider_id', $user->id)->exists();
    }

    /** Only the host who owns the listing (or an admin) may confirm, reject or check in. */
    public function manage(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || ($user->isProvider() && $user->isActive() && $booking->listing()->where('provider_id', $user->id)->exists());
    }

    /** Only the guest who made the booking may cancel it from their account. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->customer_id !== null && $booking->customer_id === $user->id;
    }
}
