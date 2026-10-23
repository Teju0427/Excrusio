<?php

namespace App\Http\Controllers\Host;

use App\Enums\ListingType;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Services\AvailabilityService;
use App\Services\HostInventoryService;
use App\Support\VenueClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Room types, prices, closed dates, weekly schedules and sessions — for the listing's own host only. */
class AvailabilityManageController extends Controller
{
    public function __construct(private HostInventoryService $inventory) {}

    public function show(Request $request, Listing $listing, AvailabilityService $availability): View
    {
        $this->authorizeHost($request, $listing);
        $listing->load('destination', 'stayUnits', 'schedules');
        $isStay = $listing->listing_type === ListingType::Stay;
        $now = VenueClock::now($listing);

        $sessions = collect();
        if (! $isStay) {
            $availability->ensureSlots($listing);
            $sessions = $listing->timeSlots()->where('starts_at', '>=', $now->format('Y-m-d H:i:s'))
                ->where('starts_at', '<', $now->addDays(7)->format('Y-m-d H:i:s'))->orderBy('starts_at')
                ->withSum(['bookings as booked' => fn ($q) => $q->whereIn('status', ['awaiting_payment', 'pending', 'confirmed'])], 'guests')->get();
        }

        return view('host.availability', [
            'listing' => $listing,
            'isStay' => $isStay,
            'units' => $listing->stayUnits->each(fn ($u) => $u->setAttribute('peak', $this->inventory->peakRoomsBooked($u))),
            'blocks' => $listing->availabilityBlocks()->where('end_date', '>', $now->toDateString())->orderBy('start_date')->get(),
            'sessions' => $sessions,
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        ]);
    }

    public function updateUnit(Request $request, Listing $listing, StayUnit $unit): RedirectResponse
    {
        $this->authorizeHost($request, $listing);
        abort_unless($unit->listing_id === $listing->id, 404);
        $data = $this->unitRules($request);

        return $this->attempt(fn () => $this->inventory->updateUnit($unit, $data), $unit->name.' updated. New prices apply to new bookings only.');
    }

    public function storeUnit(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeHost($request, $listing);
        abort_unless($listing->listing_type === ListingType::Stay, 404);
        $data = $this->unitRules($request);
        unset($data['is_active']);
        $this->inventory->addUnit($listing, $data);

        return back()->with('status', 'Room type added.');
    }

    public function storeBlock(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeHost($request, $listing);
        abort_unless($listing->listing_type === ListingType::Stay, 404);
        $today = VenueClock::now($listing)->toDateString();
        $data = $request->validate([
            'stay_unit_id' => ['nullable', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:500'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        if (! empty($data['stay_unit_id']) && ! $listing->stayUnits()->whereKey($data['stay_unit_id'])->exists()) {
            return back()->withErrors(['booking' => 'Choose one of your room types.']);
        }
        if (empty($data['stay_unit_id'])) {
            $data['rooms'] = null;   // closing every room type: the whole property
        }

        try {
            [, $affected] = $this->inventory->addBlock($listing, $data);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Dates closed to new bookings.'.($affected ? " {$affected} existing ".str('booking')->plural($affected).' in that period still stand.' : ''));
    }

    public function destroyBlock(Request $request, Listing $listing, AvailabilityBlock $block): RedirectResponse
    {
        $this->authorizeHost($request, $listing);
        abort_unless($block->listing_id === $listing->id, 404);
        $this->inventory->removeBlock($block);

        return back()->with('status', 'Those dates are open again.');
    }

    public function saveSchedules(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeHost($request, $listing);
        abort_if($listing->listing_type === ListingType::Stay, 404);
        $data = $request->validate([
            'rules' => ['required', 'array', 'min:1', 'max:28'],
            'rules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'rules.*.start_time' => ['required', 'date_format:H:i'],
            'rules.*.end_time' => ['required', 'date_format:H:i', 'different:rules.*.start_time'],
            'rules.*.interval_minutes' => ['required', 'integer', 'between:15,720'],
            'rules.*.slot_length_minutes' => ['required', 'integer', 'between:15,1440'],
            'rules.*.capacity' => ['required', 'integer', 'between:1,500'],
        ], [], ['rules.*.end_time' => 'end time', 'rules.*.capacity' => 'capacity']);

        $r = $this->inventory->saveSchedules($listing, array_values($data['rules']));

        return back()->with('status', "Schedule saved. {$r['created']} sessions created for the next 60 days"
            .($r['kept'] ? "; {$r['kept']} sessions that already have guests were kept unchanged." : '.'));
    }

    public function slotStatus(Request $request, TimeSlot $slot): RedirectResponse
    {
        $this->authorizeHost($request, $slot->listing);
        $status = $request->validate(['status' => ['required', 'in:open,closed']])['status'];

        return $this->attempt(fn () => $this->inventory->setSlotStatus($slot, $status),
            $status === 'closed' ? 'Session closed to new bookings. Guests already booked keep their places.' : 'Session reopened.');
    }

    private function unitRules(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'max_guests' => ['required', 'integer', 'between:1,30'],
            'quantity' => ['required', 'integer', 'between:0,500'],
            'price' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['price_per_night'] = (int) round($data['price'] * 100);   // rupees → paise
        $data['is_active'] = $request->boolean('is_active');
        unset($data['price']);

        return $data;
    }

    private function authorizeHost(Request $request, Listing $listing): void
    {
        abort_unless($listing->provider_id === $request->user()->id, 404);
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
