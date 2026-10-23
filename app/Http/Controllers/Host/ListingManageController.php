<?php

namespace App\Http\Controllers\Host;

use App\Enums\BookingMode;
use App\Enums\CancellationPolicy;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Listing;
use App\Services\AuditLogger;
use App\Services\SlotGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Hosts create and edit their own listings. New listings go to the admin moderation queue
 * (pending review) and are invisible to guests until approved.
 */
class ListingManageController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('create', Listing::class), 403);
        $type = ListingType::tryFrom((string) $request->query('type')) ?? ListingType::Stay;

        return view('host.listing-form', $this->formData(new Listing(['listing_type' => $type]), $type));
    }

    public function store(Request $request, SlotGenerator $slots): RedirectResponse
    {
        abort_unless($request->user()->can('create', Listing::class), 403);
        $type = ListingType::tryFrom((string) $request->input('listing_type')) ?? abort(422);
        $data = $this->validated($request, $type, creating: true);

        $listing = DB::transaction(function () use ($request, $type, $data) {
            $listing = new Listing($data['listing'] + ['listing_type' => $type]);
            $listing->slug = $this->uniqueSlug($data['listing']['title']);
            $listing->forceFill(['provider_id' => $request->user()->id, 'status' => ListingStatus::PendingReview, 'is_illustrative' => false])->save();
            $this->saveDetails($listing, $type, $data['details']);

            if ($type === ListingType::Stay) {
                $listing->stayUnits()->create($data['unit'] + ['price_per_night' => $listing->base_price, 'is_active' => true]);
            } else {
                foreach ($data['weekdays'] as $weekday) {
                    $listing->schedules()->create(['weekday' => $weekday] + $data['schedule']);
                }
            }

            return $listing;
        });

        if ($type !== ListingType::Stay) {
            $slots->generate($listing);
        }
        $this->audit->log('listing.created', $listing, [], ['title' => $listing->title, 'status' => 'pending_review']);

        return redirect()->route('host.listings.edit', $listing)
            ->with('status', 'Listing submitted. An administrator will review it before guests can see it.');
    }

    public function edit(Request $request, Listing $listing): View
    {
        abort_unless($listing->provider_id === $request->user()->id, 404);
        $listing->load('stayDetail', 'diningDetail', 'activityDetail');

        return view('host.listing-form', $this->formData($listing, $listing->listing_type));
    }

    /** Edits to a published listing go live at once (and are audit-logged); a rejected one is resubmitted. */
    public function update(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->provider_id === $request->user()->id, 404);
        $type = $listing->listing_type;
        $data = $this->validated($request, $type, creating: false);
        $old = $listing->only(array_keys($data['listing']));

        DB::transaction(function () use ($listing, $type, $data) {
            $listing->fill($data['listing']);
            if ($listing->status === ListingStatus::Rejected) {
                $listing->forceFill(['status' => ListingStatus::PendingReview, 'rejection_reason' => null]);
            }
            $listing->save();
            $this->saveDetails($listing, $type, $data['details']);
        });

        $this->audit->log('listing.updated', $listing, $old, $listing->only(array_keys($data['listing'])));

        return back()->with('status', $listing->status === ListingStatus::PendingReview
            ? 'Saved and sent for review.' : 'Saved. Changes are live; prices on existing bookings don’t change.');
    }

    // ------------------------------------------------------------------

    private function formData(Listing $listing, ListingType $type): array
    {
        return [
            'listing' => $listing,
            'type' => $type,
            'categories' => Category::where('listing_type', $type->value)->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'destinations' => Destination::orderBy('name')->get(['id', 'name', 'country']),
            'policies' => CancellationPolicy::cases(),
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        ];
    }

    private function validated(Request $request, ListingType $type, bool $creating): array
    {
        $rules = [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('listing_type', $type->value)->where('is_active', true)],
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'summary' => ['required', 'string', 'min:20', 'max:300'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'booking_mode' => ['required', Rule::enum(BookingMode::class)],
            'cancellation_policy' => ['required', Rule::enum(CancellationPolicy::class)],
        ];
        $rules += match ($type) {
            ListingType::Stay => [
                'check_in_time' => ['required', 'date_format:H:i'],
                'check_out_time' => ['required', 'date_format:H:i'],
                'min_nights' => ['required', 'integer', 'between:1,30'],
                'max_nights' => ['required', 'integer', 'between:1,90', 'gte:min_nights'],
                'house_rules' => ['nullable', 'string', 'max:2000'],
            ],
            ListingType::Dining => [
                'cuisine' => ['required', 'string', 'max:80'],
                'price_band' => ['required', 'integer', 'between:1,4'],
                'dress_code' => ['nullable', 'string', 'max:80'],
                'max_party_size' => ['required', 'integer', 'between:1,30'],
                'deposit' => ['required', 'numeric', 'min:0', 'max:100000'],
                'policies' => ['nullable', 'string', 'max:2000'],
            ],
            ListingType::Activity => [
                'duration_minutes' => ['required', 'integer', 'between:15,1440'],
                'difficulty' => ['nullable', 'in:easy,moderate,challenging'],
                'min_age' => ['nullable', 'integer', 'between:0,99'],
                'inclusions' => ['nullable', 'string', 'max:2000'],
                'equipment' => ['nullable', 'string', 'max:2000'],
                'safety_info' => ['nullable', 'string', 'max:2000'],
                'booking_cutoff_hours' => ['required', 'integer', 'between:0,168'],
            ],
        };
        if ($type === ListingType::Stay && $creating) {
            $rules['price'][2] = 'min:100';
            $rules += ['unit_name' => ['required', 'string', 'max:120'], 'unit_max_guests' => ['required', 'integer', 'between:1,30'], 'unit_quantity' => ['required', 'integer', 'between:1,500']];
        } elseif ($creating) {
            $rules += [
                'weekdays' => ['required', 'array', 'min:1'], 'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
                'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
                'interval_minutes' => ['required', 'integer', 'between:15,720'], 'slot_length_minutes' => ['required', 'integer', 'between:15,1440'],
                'capacity' => ['required', 'integer', 'between:1,500'],
            ];
        }
        $v = $request->validate($rules);

        $listing = collect($v)->only(['title', 'category_id', 'destination_id', 'summary', 'description', 'address', 'latitude', 'longitude', 'booking_mode', 'cancellation_policy'])->all();
        $listing['base_price'] = (int) round($v['price'] * 100);   // rupees → paise

        $details = match ($type) {
            ListingType::Stay => collect($v)->only(['check_in_time', 'check_out_time', 'min_nights', 'max_nights', 'house_rules'])->all(),
            ListingType::Dining => collect($v)->only(['cuisine', 'price_band', 'dress_code', 'max_party_size', 'policies'])->all()
                + ['deposit_per_guest' => (int) round($v['deposit'] * 100)],
            ListingType::Activity => collect($v)->only(['duration_minutes', 'difficulty', 'min_age', 'inclusions', 'equipment', 'safety_info', 'booking_cutoff_hours'])->all()
                + ['weather_dependent' => $request->boolean('weather_dependent')],
        };

        return [
            'listing' => $listing,
            'details' => $details,
            'unit' => $type === ListingType::Stay && $creating ? ['name' => $v['unit_name'], 'max_guests' => $v['unit_max_guests'], 'quantity' => $v['unit_quantity']] : [],
            'weekdays' => $v['weekdays'] ?? [],
            'schedule' => $creating && $type !== ListingType::Stay ? collect($v)->only(['start_time', 'end_time', 'interval_minutes', 'slot_length_minutes', 'capacity'])->all() : [],
        ];
    }

    private function saveDetails(Listing $listing, ListingType $type, array $details): void
    {
        $relation = match ($type) {
            ListingType::Stay => $listing->stayDetail(),
            ListingType::Dining => $listing->diningDetail(),
            ListingType::Activity => $listing->activityDetail(),
        };
        $relation->updateOrCreate(['listing_id' => $listing->id], $details);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        for ($i = 2; Listing::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
