<?php

namespace App\Http\Controllers\Host;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\HostCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostController extends Controller
{
    public function dashboard(Request $request, AnalyticsService $analytics): View
    {
        $user = $request->user();

        return view('host.dashboard', [
            'stats' => $analytics->host($user),
            'listings' => $user->listings()->withCount(['bookings' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'completed'])])
                ->with('destination:id,name')->orderBy('title')->get(),
        ]);
    }

    public function calendar(Request $request, HostCalendar $calendar): View
    {
        $listings = $request->user()->listings()->orderBy('title')->get(['id', 'title', 'slug', 'listing_type', 'destination_id']);
        abort_if($listings->isEmpty(), 404);

        $listing = $listings->firstWhere('slug', $request->query('listing')) ?? $listings->first();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::parse($request->query('month').'-01') : CarbonImmutable::today()->startOfMonth();
        $month = $month->max(CarbonImmutable::today()->subMonths(12)->startOfMonth())->min(CarbonImmutable::today()->addMonths(12)->startOfMonth());
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('day')) ? $request->query('day') : null;

        return view('host.calendar', [
            'listings' => $listings,
            'listing' => $listing,
            'month' => $month,
            'days' => $calendar->month($listing, $month),
            'day' => $day,
            'dayBookings' => $day ? $calendar->day($listing, $day) : collect(),
        ]);
    }

    public function pending(Request $request): View|RedirectResponse
    {
        if ($request->user()->status !== UserStatus::Pending) {
            return redirect()->route('dashboard');
        }

        return view('host.pending');
    }
}
