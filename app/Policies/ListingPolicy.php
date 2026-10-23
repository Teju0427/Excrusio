<?php

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /** Published listings are public; drafts are visible only to their host and admins. */
    public function view(?User $user, Listing $listing): bool
    {
        return $listing->status === ListingStatus::Published
            || ($user && ($user->isAdmin() || $listing->provider_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isProvider() && $user->isActive();
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->isAdmin() || ($user->isProvider() && $user->isActive() && $listing->provider_id === $user->id);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    /** Publishing / rejecting is an admin moderation action. */
    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
