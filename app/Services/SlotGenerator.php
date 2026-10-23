<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\TimeSlot;
use Carbon\CarbonImmutable;

/**
 * Turns weekly schedules ("Sat 19:00–22:00, every 30 min, 24 seats")
 * into concrete bookable time_slots for the next N days.
 *
 * Times are stored as venue-local wall-clock time (what guests see on the
 * restaurant's clock). "Now" comparisons use the destination's timezone.
 *
 * Free hosting has no cron, so this runs when a provider saves a schedule
 * and when a listing's availability is viewed (top-up).
 */
class SlotGenerator
{
    public const DAYS_AHEAD = 60;

    public function generate(Listing $listing, int $days = self::DAYS_AHEAD): int
    {
        $listing->loadMissing('schedules', 'destination');
        $tz = $listing->destination->timezone;
        $today = CarbonImmutable::now($tz)->startOfDay();
        $rows = [];

        for ($d = 0; $d < $days; $d++) {
            $date = $today->addDays($d);

            foreach ($listing->schedules->where('weekday', $date->dayOfWeek) as $rule) {
                $cursor = CarbonImmutable::parse($date->toDateString().' '.$rule->start_time);
                $windowEnd = CarbonImmutable::parse($date->toDateString().' '.$rule->end_time);
                if ($windowEnd <= $cursor) {
                    $windowEnd = $windowEnd->addDay();   // overnight sessions, e.g. camping 16:00 → 08:00
                }
                $lastStart = $windowEnd->subMinutes($rule->slot_length_minutes);

                while ($cursor <= $lastStart) {
                    $rows[] = [
                        'listing_id' => $listing->id,
                        'starts_at' => $cursor->format('Y-m-d H:i:s'),
                        'ends_at' => $cursor->addMinutes($rule->slot_length_minutes)->format('Y-m-d H:i:s'),
                        'capacity' => $rule->capacity,
                        'status' => 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $cursor = $cursor->addMinutes($rule->interval_minutes);
                }
            }
        }

        // insertOrIgnore + unique(listing_id, starts_at) = safe to run repeatedly.
        $inserted = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $inserted += TimeSlot::insertOrIgnore($chunk);
        }

        return $inserted;
    }
}
