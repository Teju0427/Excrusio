<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds synthetic demo data only. Safe to re-run (uses updateOrCreate).
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            DestinationSeeder::class,
            UserSeeder::class,
            ListingSeeder::class,
        ]);

        // Booking history for the dashboards. Skipped in automated tests, which need predictable availability.
        if (! app()->environment('testing')) {
            $this->call([DemoBookingSeeder::class, DemoReviewSeeder::class]);
        }
    }
}
