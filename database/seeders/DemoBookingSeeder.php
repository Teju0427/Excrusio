<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ListingType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Synthetic booking history (past 90 days + next 60) so dashboards and charts show real,
 * database-calculated numbers. Capacity is respected night by night and slot by slot.
 * Not run during automated tests (they need predictable availability).
 */
class DemoBookingSeeder extends Seeder
{
    private const NAMES = ['Ananya Iyer', 'Rohan Mehta', 'Priya Nair', 'Arjun Reddy', 'Meera Kapoor', 'Vikram Shah', 'Sara Thomas',
        'Kabir Singh', 'Isha Banerjee', 'Dev Malhotra', 'Neha Joshi', 'Aditya Rao', 'Hana Sato', 'Lukas Meier'];

    private array $customers = [];

    public function run(PricingService $pricing): void
    {
        mt_srand(2027);   // same demo data on every machine
        $password = Hash::make(Str::random(32));   // these synthetic guests can't sign in
        foreach (self::NAMES as $name) {
            $user = User::firstOrNew(['email' => Str::slug($name, '.').'@guests.excursio.test']);
            $user->name = $name;
            $user->password = $password;
            $user->forceFill(['role' => UserRole::Customer, 'status' => UserStatus::Active])->save();
            $this->customers[] = $user;
        }
        // The demo guest gets a few bookings of their own too.
        $this->customers[] = User::where('email', 'guest@excursio.test')->first();

        $today = CarbonImmutable::today();
        $listings = Listing::with('stayUnits', 'stayDetail', 'schedules', 'destination', 'diningDetail', 'activityDetail')->get();

        foreach ($listings->where('listing_type', ListingType::Stay) as $listing) {
            foreach ($listing->stayUnits as $unit) {
                $day = $today->subDays(90);
                while ($day->lessThan($today->addDays(60))) {
                    if (mt_rand(1, 100) <= 40) {
                        $nights = max($listing->stayDetail?->min_nights ?? 1, mt_rand(1, 4));
                        $rooms = mt_rand(1, min(2, $unit->quantity));
                        $out = $day->addDays($nights);
                        $quote = $pricing->quoteStay($unit, $day, $out, $rooms);
                        $this->make($listing, [
                            'stay_unit_id' => $unit->id, 'check_in' => $day->toDateString(), 'check_out' => $out->toDateString(),
                            'starts_at' => $day->toDateString().' 14:00:00', 'ends_at' => $out->toDateString().' 11:00:00',
                            'nights' => $nights, 'rooms' => $rooms, 'guests' => min($unit->max_guests * $rooms, mt_rand(1, 2) * $rooms),
                            'unit_price' => $quote['unit_price'], 'subtotal' => $quote['subtotal'], 'fees' => $quote['fees'], 'total' => $quote['total'],
                            'price_breakdown' => ['nights' => $quote['nights'], 'rooms' => $rooms, 'unit' => $unit->name],
                        ], $today, $listing->booking_mode->value === 'request');
                        $day = $out->addDays(mt_rand(0, 3));   // one booking per room type at a time → capacity always respected
                    } else {
                        $day = $day->addDays(mt_rand(1, 3));
                    }
                }
            }
        }

        foreach ($listings->where('listing_type', '!=', ListingType::Stay) as $listing) {
            for ($offset = -90; $offset < 45; $offset++) {
                $date = $today->addDays($offset);
                $rule = $listing->schedules->firstWhere('weekday', $date->dayOfWeek);
                if (! $rule || mt_rand(1, 100) > ($offset < 0 ? 40 : 25)) {
                    continue;
                }
                $slot = TimeSlot::firstOrCreate(
                    ['listing_id' => $listing->id, 'starts_at' => $date->toDateString().' '.$rule->start_time],
                    ['ends_at' => CarbonImmutable::parse($date->toDateString().' '.$rule->start_time)->addMinutes($rule->slot_length_minutes), 'capacity' => $rule->capacity, 'status' => 'open']
                );
                $remaining = $slot->capacity - (int) Booking::where('time_slot_id', $slot->id)->whereIn('status', ['confirmed', 'completed', 'pending'])->sum('guests');
                $parties = mt_rand(1, 3);
                for ($p = 0; $p < $parties && $remaining > 0; $p++) {
                    $guests = min($remaining, mt_rand(1, min(4, $listing->diningDetail?->max_party_size ?? 4)));
                    $quote = $pricing->quoteSlot($listing, $slot, $guests);
                    $booking = $this->make($listing, [
                        'time_slot_id' => $slot->id, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'guests' => $guests,
                        'unit_price' => $quote['unit_price'], 'subtotal' => $quote['subtotal'], 'fees' => $quote['fees'], 'total' => $quote['total'],
                        'price_breakdown' => ['per_person' => $quote['unit_price'], 'guests' => $guests],
                    ], $today, false);
                    if ($booking->status !== BookingStatus::Cancelled) {
                        $remaining -= $guests;
                    }
                }
            }
        }
        $this->seedNotifications();
    }

    /** A few in-app notifications so the demo accounts' bells aren't empty. */
    private function seedNotifications(): void
    {
        $guest = User::where('email', 'guest@excursio.test')->first();
        $guest->bookings()->with('listing:id,title')->where('status', 'confirmed')->where('starts_at', '>', now())
            ->orderBy('starts_at')->limit(3)->get()
            ->each(fn ($b) => $guest->notify(new \App\Notifications\BookingNotification($b, 'Your booking at '.$b->listing->title.' is confirmed.')));

        Booking::with('listing.provider')->where('status', 'pending')->get()
            ->each(fn ($b) => $b->listing->provider->notify(new \App\Notifications\BookingNotification($b, 'New booking request for '.$b->listing->title.' — please respond within 24 hours.')));
    }

    private function make(Listing $listing, array $attrs, CarbonImmutable $today, bool $requestMode): Booking
    {
        $customer = $this->customers[array_rand($this->customers)];
        $start = CarbonImmutable::parse($attrs['starts_at']);
        $end = CarbonImmutable::parse($attrs['ends_at']);
        $past = $end->lessThan($today);
        $roll = mt_rand(1, 100);
        $offline = mt_rand(1, 100) <= 8;

        $status = match (true) {
            $roll <= 9 => BookingStatus::Cancelled,
            $past => BookingStatus::Completed,
            $requestMode && $start->lessThan($today->addDays(45)) && $roll <= 18 => BookingStatus::Pending,
            default => BookingStatus::Confirmed,
        };
        $paid = $attrs['total'] > 0 && ! $offline;
        // Booked 2–35 days ahead; bookings for future dates are spread over the last few weeks, never in the future.
        $created = $start->subDays(mt_rand(2, 35))->subMinutes(mt_rand(0, 600));
        if ($created->greaterThan(now()->subHour())) {
            $created = CarbonImmutable::now()->subMinutes(mt_rand(60, 60 * 24 * 40));
        }
        $refund = $status === BookingStatus::Cancelled && $paid ? (mt_rand(0, 1) ? $attrs['total'] : intdiv($attrs['total'], 2)) : null;

        $booking = new Booking(['guest_name' => $offline ? 'Phone guest '.mt_rand(100, 999) : $customer->name, 'guest_email' => $offline ? null : $customer->email]);
        $booking->forceFill(array_merge($attrs, [
            'reference' => 'EXC-'.$listing->listing_type->code().'-'.Str::upper(Str::random(6)),
            'source' => $offline ? 'offline' : 'online',
            'customer_id' => $offline ? null : $customer->id,
            'listing_id' => $listing->id, 'listing_type' => $listing->listing_type,
            'cancellation_policy' => $listing->cancellation_policy,
            'status' => $status,
            'payment_status' => ! $paid ? PaymentStatus::NotRequired
                : ($refund === null ? PaymentStatus::DemoPaid : ($refund >= $attrs['total'] ? PaymentStatus::DemoRefunded : PaymentStatus::DemoPartiallyRefunded)),
            'confirmed_at' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed]) ? $created->addMinutes(30) : null,
            'response_due_at' => $status === BookingStatus::Pending ? now()->addHours(mt_rand(3, 23)) : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? $created->addDays(1) : null,
            'cancellation_reason' => $status === BookingStatus::Cancelled ? 'Change of plans' : null,
            'refund_amount' => $refund,
            'checkin_token' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed]) ? Str::random(40) : null,
            'checked_in_at' => $status === BookingStatus::Completed ? $start : null,
            'created_at' => $created, 'updated_at' => $created,
        ]))->save();

        if ($paid) {
            Payment::create(['booking_id' => $booking->id, 'gateway' => 'demo', 'gateway_reference' => 'DEMO-'.Str::upper(Str::random(10)), 'type' => 'charge', 'amount' => $attrs['total'], 'status' => 'succeeded'])
                ->forceFill(['created_at' => $created, 'updated_at' => $created])->save();
            if ($refund) {
                Payment::create(['booking_id' => $booking->id, 'gateway' => 'demo', 'gateway_reference' => 'DEMO-RF-'.Str::upper(Str::random(8)), 'type' => 'refund', 'amount' => $refund, 'status' => 'succeeded'])
                    ->forceFill(['created_at' => $created->addDay(), 'updated_at' => $created->addDay()])->save();
            }
        }

        return $booking;
    }
}
