<?php

namespace App\Observers;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

class ListingObserver
{
    /** Any listing change refreshes the cached homepage rows. */
    public function saved(Listing $listing): void
    {
        Cache::forget('home:rows');
    }

    public function deleted(Listing $listing): void
    {
        Cache::forget('home:rows');
    }
}
