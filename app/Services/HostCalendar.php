<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Models\Booking;
use App\Models\Listing;
use Carbon\CarbonImmutable;

/** Month view for one listing: how full each day is, from bookings and closures in the database. */
class HostCalendar
{
    private const COUNTED = ['pending', 'confirmed', 'completed'];

    public function month(Listing $listing, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();
        $days = [];

        if ($listing->listing_type === ListingType::Stay) {
            $units = $listing->stayUnits()->where('is_active', true)->get();
            $capacity = (int) $units->sum('quantity');
            $bookings = Booking::whereIn('stay_unit_id', $units->pluck('id'))->whereIn('status', self::COUNTED)
                ->where('check_in', '<=', $to->toDateString())->where('check_out', '>', $from->toDateString())
                ->get(['check_in', 'check_out', 'rooms']);
            $blocks = $listing->availabilityBlocks()->where('start_date', '<=', $to->toDateString())->where('end_date', '>', $from->toDateString())->get();

            for ($d = $from; $d <= $to; $d = $d->addDay()) {
                $date = $d->toDateString();
                $night = $bookings->filter(fn ($b) => $b->check_in->toDateString() <= $date && $b->check_out->toDateString() > $date);
                $closed = $blocks->filter(fn ($b) => $b->start_date->toDateString() <= $date && $b->end_date->toDateString() > $date);
                $days[$date] = [
                    'used' => (int) $night->sum('rooms'),
                    'capacity' => $capacity,
                    'arrivals' => $bookings->filter(fn ($b) => $b->check_in->toDateString() === $date)->count(),
                    'blocked' => $closed->isNotEmpty(),
                    'unit' => 'rooms',
                ];
            }

            return $days;
        }

        $slots = $listing->timeSlots()->whereBetween('starts_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'])
            ->get(['id', 'starts_at', 'capacity', 'status']);
        $taken = Booking::whereIn('time_slot_id', $slots->pluck('id'))->whereIn('status', self::COUNTED)
            ->selectRaw('time_slot_id, sum(guests) g, count(*) n')->groupBy('time_slot_id')->get()->keyBy('time_slot_id');

        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $daySlots = $slots->filter(fn ($s) => $s->starts_at->toDateString() === $d->toDateString());
            $open = $daySlots->where('status', 'open');
            $days[$d->toDateString()] = [
                'used' => (int) $daySlots->sum(fn ($s) => $taken[$s->id]->g ?? 0),
                'capacity' => (int) $open->sum('capacity'),
                'arrivals' => (int) $daySlots->sum(fn ($s) => $taken[$s->id]->n ?? 0),
                'blocked' => $daySlots->isNotEmpty() && $open->isEmpty(),
                'unit' => $listing->listing_type === ListingType::Dining ? 'seats' : 'places',
            ];
        }

        return $days;
    }

    /** Everything happening at the listing on one date (stays in-house that night, or sessions that day). */
    public function day(Listing $listing, string $date)
    {
        $q = Booking::where('listing_id', $listing->id)->whereIn('status', ['pending', 'confirmed', 'completed', 'cancelled'])
            ->with('stayUnit:id,name');

        return $listing->listing_type === ListingType::Stay
            ? $q->where('check_in', '<=', $date)->where('check_out', '>', $date)->orderBy('check_in')->get()
            : $q->whereDate('starts_at', $date)->orderBy('starts_at')->get();
    }
}
