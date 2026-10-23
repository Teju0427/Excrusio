<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the host and admin dashboards is calculated here from database records.
 * Money figures come from recorded (demo) payments minus refunds — never typed in.
 */
class AnalyticsService
{
    /** Bookings that really happened or will happen (not abandoned holds). */
    private const REAL = ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'];

    private const LIVE = ['pending', 'confirmed', 'completed'];

    // ================================================================= ADMIN

    public function admin(): array
    {
        $weeks = $this->weeks(12);

        return [
            'users' => User::selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role'),
            'listings' => Listing::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'bookingsTotal' => Booking::whereIn('status', self::REAL)->count(),
            'byType' => $this->byType(Booking::query()),
            'byStatus' => Booking::whereIn('status', self::REAL)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'trend' => $this->weeklyTrendByType(Booking::query(), $weeks),
            'weeks' => $weeks->map(fn ($w) => $w->format('j M'))->all(),
            'destinations' => Booking::whereIn('bookings.status', self::LIVE)
                ->join('listings', 'listings.id', '=', 'bookings.listing_id')
                ->join('destinations', 'destinations.id', '=', 'listings.destination_id')
                ->selectRaw('destinations.name, count(*) n')->groupBy('destinations.name')->orderByDesc('n')->limit(8)->pluck('n', 'name'),
            'topActivities' => Booking::whereIn('bookings.status', self::LIVE)->where('bookings.listing_type', 'activity')
                ->join('listings', 'listings.id', '=', 'bookings.listing_id')
                ->selectRaw('listings.title, count(*) bookings, sum(bookings.guests) people')
                ->groupBy('listings.title')->orderByDesc('bookings')->limit(5)->get(),
            'revenue30' => $this->netRevenue(null, now()->subDays(30)),
            'cancellationRate' => $this->rate(Booking::whereIn('status', self::REAL)->where('created_at', '>=', now()->subDays(90))),
            'moderation' => Listing::where('status', ListingStatus::PendingReview->value)->count(),
        ];
    }

    // ================================================================== HOST

    public function host(User $host): array
    {
        $listingIds = $host->listings()->pluck('id');
        $bookings = Booking::whereIn('listing_id', $listingIds);
        $weeks = $this->weeks(12);

        return [
            'listingCount' => $listingIds->count(),
            'pending' => (clone $bookings)->where('status', 'pending')->count(),
            'upcoming30' => (clone $bookings)->where('status', 'confirmed')->whereBetween('starts_at', [now(), now()->addDays(30)])->count(),
            'revenue30' => $this->netRevenue($listingIds, now()->subDays(30)),
            'occupancy' => $this->stayOccupancy($listingIds, 30),
            'seatFill' => $this->slotFill($listingIds, 14),
            'trend' => $this->weeklyTrendByType((clone $bookings), $weeks),
            'weeks' => $weeks->map(fn ($w) => $w->format('j M'))->all(),
            'perListing' => Booking::whereIn('bookings.listing_id', $listingIds)->whereIn('bookings.status', self::LIVE)
                ->where('bookings.created_at', '>=', now()->subDays(90))
                ->join('listings', 'listings.id', '=', 'bookings.listing_id')
                ->selectRaw('listings.title, count(*) n')->groupBy('listings.title')->orderByDesc('n')->pluck('n', 'title'),
        ];
    }

    /** Share of room-nights booked over the next N days, across the host's stays. */
    public function stayOccupancy(Collection $listingIds, int $days): ?int
    {
        $units = StayUnit::whereIn('listing_id', $listingIds)->where('is_active', true)->get();
        $capacity = $units->sum('quantity') * $days;
        if ($capacity === 0) {
            return null;
        }
        $from = CarbonImmutable::today();
        $to = $from->addDays($days);
        $booked = 0;
        Booking::whereIn('stay_unit_id', $units->pluck('id'))->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '<', $to->toDateString())->where('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out', 'rooms'])->each(function ($b) use (&$booked, $from, $to) {
                $start = $b->check_in->max($from);
                $end = $b->check_out->min($to);
                $booked += max(0, $start->diffInDays($end)) * $b->rooms;
            });

        return (int) round($booked / $capacity * 100);
    }

    /** Share of seats/places booked over the next N days, across the host's sessions. */
    public function slotFill(Collection $listingIds, int $days): ?int
    {
        $slots = TimeSlot::whereIn('listing_id', $listingIds)->where('status', 'open')->whereBetween('starts_at', [now(), now()->addDays($days)]);
        $capacity = (clone $slots)->sum('capacity');
        if (! $capacity) {
            return null;
        }
        $taken = Booking::whereIn('time_slot_id', (clone $slots)->pluck('id'))->whereIn('status', ['pending', 'confirmed'])->sum('guests');

        return (int) round($taken / $capacity * 100);
    }

    // ============================================================== HELPERS

    /** @return Collection<int, CarbonImmutable> Monday of each of the last N weeks */
    private function weeks(int $n): Collection
    {
        $thisWeek = CarbonImmutable::now()->startOfWeek();

        return collect(range($n - 1, 0))->map(fn ($i) => $thisWeek->subWeeks($i));
    }

    private function byType($query): array
    {
        $counts = (clone $query)->whereIn('status', self::LIVE)->selectRaw('listing_type, count(*) n')->groupBy('listing_type')->pluck('n', 'listing_type');

        return collect(ListingType::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($counts[$t->value] ?? 0)])->all();
    }

    /** New bookings per week (by booking date), one series per category. */
    private function weeklyTrendByType($query, Collection $weeks): array
    {
        $rows = (clone $query)->whereIn('status', self::REAL)->where('created_at', '>=', $weeks->first())
            ->get(['listing_type', 'created_at']);
        $series = [];
        foreach (ListingType::cases() as $type) {
            $series[$type->value] = $weeks->map(fn ($w) => $rows->filter(fn ($r) => $r->listing_type === $type
                && $r->created_at->greaterThanOrEqualTo($w) && $r->created_at->lessThan($w->addWeek()))->count())->all();
        }

        return $series;
    }

    /** Demo charges minus demo refunds, in paise. */
    private function netRevenue(?Collection $listingIds, $since): int
    {
        $q = Payment::where('payments.created_at', '>=', $since)->where('payments.status', 'succeeded');
        if ($listingIds) {
            $q->join('bookings', 'bookings.id', '=', 'payments.booking_id')->whereIn('bookings.listing_id', $listingIds);
        }
        $sums = $q->selectRaw("payments.type, SUM(payments.amount) total")->groupBy('payments.type')->pluck('total', 'type');

        return (int) ($sums['charge'] ?? 0) - (int) ($sums['refund'] ?? 0);
    }

    private function rate($query): ?int
    {
        $total = (clone $query)->count();

        return $total ? (int) round((clone $query)->where('status', 'cancelled')->count() / $total * 100) : null;
    }
}
