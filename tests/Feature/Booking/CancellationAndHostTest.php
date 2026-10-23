<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\ListingType;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\User;
use App\Services\BookingMaintenance;
use App\Services\BookingService;
use App\Services\CancellationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationAndHostTest extends TestCase
{
    use RefreshDatabase;

    private function paidStay(string $unitName, int $daysAhead, ?User $user = null): Booking
    {
        $user ??= User::factory()->customer()->create();
        $svc = app(BookingService::class);
        $unit = StayUnit::where('name', $unitName)->first();
        $in = CarbonImmutable::now()->addDays($daysAhead);
        $booking = $svc->holdStay($user, $unit, $in->toDateString(), $in->addDays(2)->toDateString(), 1, 2);

        return $svc->checkout($booking, ['guest_name' => $user->name, 'guest_email' => $user->email]);
    }

    public function test_refund_rules_by_policy(): void
    {
        $svc = app(CancellationService::class);
        $start = CarbonImmutable::parse('2027-03-20 14:00');
        $at = fn (string $t) => CarbonImmutable::parse($t);

        $this->assertSame(100, $svc->percentAt(CancellationPolicy::Flexible, ListingType::Stay, $start, $at('2027-03-19 13:00')));
        $this->assertSame(0, $svc->percentAt(CancellationPolicy::Flexible, ListingType::Stay, $start, $at('2027-03-19 15:00')));
        $this->assertSame(100, $svc->percentAt(CancellationPolicy::Moderate, ListingType::Stay, $start, $at('2027-03-14 12:00')));
        $this->assertSame(50, $svc->percentAt(CancellationPolicy::Moderate, ListingType::Stay, $start, $at('2027-03-17 12:00')));
        $this->assertSame(0, $svc->percentAt(CancellationPolicy::Moderate, ListingType::Stay, $start, $at('2027-03-20 10:00')));
        $this->assertSame(50, $svc->percentAt(CancellationPolicy::Strict, ListingType::Stay, $start, $at('2027-03-10 12:00')));
        $this->assertSame(0, $svc->percentAt(CancellationPolicy::Strict, ListingType::Stay, $start, $at('2027-03-15 12:00')));
        // Dining: free until 2 hours before, whatever the listing policy says
        $this->assertSame(100, $svc->percentAt(CancellationPolicy::Strict, ListingType::Dining, $start, $at('2027-03-20 11:30')));
        $this->assertSame(0, $svc->percentAt(CancellationPolicy::Strict, ListingType::Dining, $start, $at('2027-03-20 12:30')));
    }

    public function test_refund_timeline_amounts(): void
    {
        $rows = app(CancellationService::class)->timeline(CancellationPolicy::Moderate, ListingType::Stay, CarbonImmutable::parse('2027-03-20 14:00'), 1000000);

        $this->assertSame([100, 50, 0], array_column($rows, 'percent'));
        $this->assertSame([1000000, 500000, 0], array_column($rows, 'amount'));
        $this->assertSame('2027-03-15 14:00', $rows[0]['until']->format('Y-m-d H:i'));
        $this->assertSame('2027-03-19 14:00', $rows[1]['until']->format('Y-m-d H:i'));
    }

    public function test_guest_cancels_confirmed_stay_and_gets_policy_refund(): void
    {
        $user = User::factory()->customer()->create();
        $booking = $this->paidStay('Aurora Glass Cabin', 30, $user);   // moderate policy, 30 days out → 100%

        $this->actingAs($user)->get(route('bookings.show', $booking))->assertSee('If you cancel now, you’ll get');
        $this->post(route('bookings.cancel', $booking), ['reason' => 'Change of plans'])->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame($booking->total, $booking->refund_amount);
        $this->assertSame(PaymentStatus::DemoRefunded, $booking->payment_status);
        $this->assertSame(1, $booking->payments()->where('type', 'refund')->count());
    }

    public function test_late_cancellation_gets_partial_refund(): void
    {
        $user = User::factory()->customer()->create();
        $booking = $this->paidStay('Aurora Glass Cabin', 3, $user);   // moderate, 3 days out → 50%

        $this->actingAs($user)->post(route('bookings.cancel', $booking));
        $booking->refresh();
        $this->assertSame((int) round($booking->total / 2), $booking->refund_amount);
        $this->assertSame(PaymentStatus::DemoPartiallyRefunded, $booking->payment_status);
    }

    public function test_other_users_cannot_cancel(): void
    {
        $booking = $this->paidStay('Aurora Glass Cabin', 30);
        $this->actingAs(User::factory()->customer()->create())->post(route('bookings.cancel', $booking))->assertNotFound();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_cancelling_frees_the_room(): void
    {
        $user = User::factory()->customer()->create();
        $booking = $this->paidStay('Family Loft', 40, $user);
        $this->actingAs($user)->post(route('bookings.cancel', $booking));

        $again = $this->paidStay('Family Loft', 40);   // would throw if the room were still taken
        $this->assertSame(BookingStatus::Pending, $again->status);   // chalet is request-to-book
    }

    public function test_host_accepts_and_declines_requests(): void
    {
        $listing = Listing::where('slug', 'matterhorn-view-chalet')->first();
        $host = $listing->provider;
        $a = $this->paidStay('Alpine Double', 20);
        $b = $this->paidStay('Alpine Double', 25);

        $this->actingAs($host)->get('/host/bookings')->assertOk()->assertSee($a->guest_name)->assertSee('Requests to answer');
        $this->post(route('host.bookings.confirm', $a))->assertSessionHas('status');
        $this->post(route('host.bookings.reject', $b), ['reason' => 'Closed for maintenance'])->assertSessionHas('status');

        $this->assertSame(BookingStatus::Confirmed, $a->fresh()->status);
        $this->assertNotNull($a->fresh()->checkin_token);
        $this->assertSame(BookingStatus::Rejected, $b->fresh()->status);
        $this->assertSame($b->total, $b->fresh()->refund_amount);
        $this->assertSame(1, $a->customer->notifications()->where('data', 'like', '%confirmed%')->count());
    }

    public function test_other_hosts_cannot_answer_requests(): void
    {
        $booking = $this->paidStay('Alpine Double', 20);
        $this->actingAs(User::factory()->provider()->create())->post(route('host.bookings.confirm', $booking))->assertNotFound();
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_unanswered_request_expires_with_full_refund(): void
    {
        $booking = $this->paidStay('Alpine Double', 20);
        $this->travel(25)->hours();
        app(BookingMaintenance::class)->run();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertSame($booking->total, $booking->refund_amount);
        $this->actingAs($booking->listing->provider)->post(route('host.bookings.confirm', $booking))->assertSessionHasErrors('booking');
    }

    public function test_finished_bookings_become_completed(): void
    {
        $booking = $this->paidStay('Aurora Glass Cabin', 1);
        $this->travel(5)->days();
        app(BookingMaintenance::class)->run();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_offline_booking_uses_the_same_inventory(): void
    {
        $listing = Listing::where('slug', 'matterhorn-view-chalet')->first();
        $loft = StayUnit::where('name', 'Family Loft')->first();
        $in = CarbonImmutable::now()->addDays(50)->toDateString();
        $out = CarbonImmutable::now()->addDays(52)->toDateString();

        $this->actingAs($listing->provider)->get(route('host.offline.create', $listing))->assertOk();
        $this->post(route('host.offline.store', $listing), ['guest_name' => 'Walk-in Guest', 'guest_phone' => '+41 79 000 00 00', 'stay_unit_id' => $loft->id, 'check_in' => $in, 'check_out' => $out, 'rooms' => 1, 'guests' => 3])
            ->assertRedirect(route('host.bookings'));

        $offline = Booking::where('source', 'offline')->first();
        $this->assertNull($offline->customer_id);
        $this->assertSame(BookingStatus::Confirmed, $offline->status);

        $this->getJson("/api/listings/matterhorn-view-chalet/stay?check_in={$in}&check_out={$out}&guests=2")
            ->assertJsonFragment(['name' => 'Family Loft', 'available' => false]);
        $this->actingAs(User::factory()->customer()->create())->post('/book/stay/matterhorn-view-chalet', ['stay_unit_id' => $loft->id, 'check_in' => $in, 'check_out' => $out, 'rooms' => 1, 'guests' => 2])
            ->assertSessionHasErrors('booking');
        // Another host can't add offline bookings to this listing
        $this->actingAs(User::factory()->provider()->create())->get(route('host.offline.create', $listing))->assertNotFound();
    }

    public function test_weather_cancellation_refunds_everyone_on_the_session(): void
    {
        $listing = Listing::where('slug', 'sunset-mangrove-kayaking')->first();
        $slot = $listing->timeSlots()->where('starts_at', '>', CarbonImmutable::now($listing->destination->timezone)->addDays(3)->format('Y-m-d H:i:s'))->first();
        $svc = app(BookingService::class);
        $people = [];
        foreach ([2, 3] as $n) {
            $u = User::factory()->customer()->create();
            $b = $svc->holdSlot($u, $slot, $n);
            $people[] = $svc->checkout($b, ['guest_name' => $u->name, 'guest_email' => $u->email, 'acknowledged' => true,
                'participants' => array_fill(0, $n, ['full_name' => 'P', 'age' => 30])]);
        }

        $this->actingAs($listing->provider)->post(route('host.slots.weather-cancel', $slot), ['reason' => 'Storm warning'])->assertSessionHas('status');

        $this->assertSame('cancelled', $slot->fresh()->status);
        foreach ($people as $b) {
            $b->refresh();
            $this->assertSame(BookingStatus::Cancelled, $b->status);
            $this->assertSame($b->total, $b->refund_amount);
            $this->actingAs($b->customer)->get(route('bookings.show', $b))->assertSee('Rebook another session');
        }
        $this->getJson("/api/listings/sunset-mangrove-kayaking/slots?date={$slot->starts_at->toDateString()}&guests=1")->assertJsonMissing(['id' => $slot->id]);
    }

    public function test_illegal_status_changes_are_refused(): void
    {
        $user = User::factory()->customer()->create();
        $booking = $this->paidStay('Aurora Glass Cabin', 30, $user);
        $this->actingAs($user)->post(route('bookings.cancel', $booking));
        $this->post(route('bookings.cancel', $booking))->assertSessionHasErrors(['booking' => 'This booking can no longer be cancelled.']);

        $this->expectException(BookingException::class);
        app(BookingService::class)->confirmRequest($booking->fresh(), User::factory()->admin()->create());
    }
}
