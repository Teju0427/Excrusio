<?php

namespace App\Support;

use App\Models\Listing;
use Carbon\CarbonImmutable;

/**
 * Booking times are stored as the venue's local wall-clock time ("19:30 in Kyoto").
 * This returns "now" at the venue in the same form, so the two can be compared directly.
 */
class VenueClock
{
    public static function now(Listing $listing): CarbonImmutable
    {
        $listing->loadMissing('destination');

        return CarbonImmutable::parse(CarbonImmutable::now($listing->destination->timezone)->format('Y-m-d H:i:s'));
    }
}
