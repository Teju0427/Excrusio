<?php

namespace App\Services;

use App\Enums\CancellationPolicy;
use App\Enums\ListingType;
use App\Models\Booking;
use App\Support\VenueClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Refund rules, shown to guests *before* they book (feature F5) and applied when they cancel.
 * The policy is copied onto each booking, so later policy edits never change old bookings.
 *
 *   Flexible: 100% until 24 h before start, then 0%
 *   Moderate: 100% until 5 days before, 50% until 24 h before, then 0%
 *   Strict:   50% until 7 days before, then 0%
 *   Dining (any policy): free cancellation until 2 h before
 */
class CancellationService
{
    /**
     * Refund windows as [hours-before-start => percent], most generous first.
     *
     * @return list<array{hours: int, percent: int}>
     */
    public function rules(CancellationPolicy $policy, ListingType $type): array
    {
        if ($type === ListingType::Dining) {
            return [['hours' => 2, 'percent' => 100]];
        }

        return match ($policy) {
            CancellationPolicy::Flexible => [['hours' => 24, 'percent' => 100]],
            CancellationPolicy::Moderate => [['hours' => 120, 'percent' => 100], ['hours' => 24, 'percent' => 50]],
            CancellationPolicy::Strict => [['hours' => 168, 'percent' => 50]],
        };
    }

    /** Percent refunded if cancelled at $now (venue local time). */
    public function percentAt(CancellationPolicy $policy, ListingType $type, CarbonInterface $startsAt, CarbonInterface $now): int
    {
        $hoursBefore = $now->diffInMinutes($startsAt, false) / 60;
        foreach ($this->rules($policy, $type) as $rule) {
            if ($hoursBefore >= $rule['hours']) {
                return $rule['percent'];
            }
        }

        return 0;
    }

    /** @return array{percent: int, amount: int} amount in paise */
    public function refundFor(Booking $booking, ?CarbonInterface $now = null): array
    {
        $now ??= VenueClock::now($booking->listing);
        $percent = $this->percentAt($booking->cancellation_policy, $booking->listing_type, $booking->starts_at, $now);

        return ['percent' => $percent, 'amount' => (int) round($booking->total * $percent / 100)];
    }

    /**
     * Human-readable refund timeline for a start time and total, e.g.
     *   Until Sat 14 Dec, 14:00 → ₹12,000 back (100%)
     *   Until Wed 18 Dec, 14:00 → ₹6,000 back (50%)
     *   After that → no refund
     *
     * @return list<array{until: ?CarbonImmutable, percent: int, amount: int, past: bool}>
     */
    public function timeline(CancellationPolicy $policy, ListingType $type, CarbonInterface $startsAt, int $total, ?CarbonInterface $now = null): array
    {
        $start = CarbonImmutable::instance($startsAt);
        $rows = [];
        foreach ($this->rules($policy, $type) as $rule) {
            $until = $start->subHours($rule['hours']);
            $rows[] = ['until' => $until, 'percent' => $rule['percent'], 'amount' => (int) round($total * $rule['percent'] / 100), 'past' => $now ? $now->greaterThan($until) : false];
        }
        $rows[] = ['until' => null, 'percent' => 0, 'amount' => 0, 'past' => false];

        return $rows;
    }
}
