<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\StayUnit;
use App\Models\User;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function d(int $days): string
    {
        return CarbonImmutable::now()->addDays($days)->toDateString();
    }

    private function guest(): User
    {
        return User::factory()->customer()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
    }

    private function details(array $extra = []): array
    {
        return array_merge(['guest_name' => 'Asha Rao', 'guest_email' => 'asha@example.com', 'guest_phone' => '+91 98765 43210'], $extra);
    }

    private function futureSlot(string $slug, int $days = 5)
    {
        $listing = Listing::where('slug', $slug)->first();
        $tz = $listing->destination->timezone;

        return $listing->timeSlots()->where('status', 'open')
            ->where('starts_at', '>', CarbonImmutable::now($tz)->addDays($days)->format('Y-m-d H:i:s'))->orderBy('starts_at')->first();
    }

    public function test_instant_stay_full_flow_hold_checkout_confirmed(): void
    {
        $user = $this->guest();
        $unit = StayUnit::where('name', 'Aurora Glass Cabin')->first();   // instant booking listing

        $res = $this->actingAs($user)->post('/book/stay/black-sand-glass-cabins', [
            'stay_unit_id' => $unit->id, 'check_in' => $this->d(20), 'check_out' => $this->d(22), 'rooms' => 1, 'guests' => 2,
        ]);
        $booking = Booking::latest('id')->first();
        $res->assertRedirect(route('checkout.show', $booking));
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->status);
        $this->assertNotNull($booking->hold_expires_at);

        $this->get(route('checkout.show', $booking))->assertOk()->assertSee('Demo payment')->assertSee('If your plans change');
        $this->post(route('checkout.pay', $booking), $this->details())->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::DemoPaid, $booking->payment_status);
        $this->assertNotNull($booking->checkin_token);
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->where('type', 'charge')->where('amount', $booking->total)->count());
        $this->get(route('bookings.show', $booking))->assertSee('Confirmed')->assertSee($booking->reference);
        // host was notified
        $this->assertSame(1, Listing::where('slug', 'black-sand-glass-cabins')->first()->provider->notifications()->count());
    }

    public function test_request_to_book_stays_wait_for_the_hotel(): void
    {
        $user = $this->guest();
        $unit = StayUnit::where('name', 'Alpine Double')->first();   // Matterhorn chalet = request mode
        $this->actingAs($user)->post('/book/stay/matterhorn-view-chalet', ['stay_unit_id' => $unit->id, 'check_in' => $this->d(20), 'check_out' => $this->d(22), 'rooms' => 1, 'guests' => 2]);
        $booking = Booking::latest('id')->first();
        $this->post(route('checkout.pay', $booking), $this->details());

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNotNull($booking->response_due_at);
        $this->assertNull($booking->checkin_token);
    }

    public function test_server_ignores_any_price_sent_by_the_browser(): void
    {
        $user = $this->guest();
        $unit = StayUnit::where('name', 'Aurora Glass Cabin')->first();
        $this->actingAs($user)->post('/book/stay/black-sand-glass-cabins', [
            'stay_unit_id' => $unit->id, 'check_in' => $this->d(20), 'check_out' => $this->d(22), 'rooms' => 1, 'guests' => 2,
            'total' => 1, 'unit_price' => 1, 'subtotal' => 1,
        ]);
        $booking = Booking::latest('id')->first();
        $expected = app(PricingService::class)->quoteStay($unit, CarbonImmutable::parse($this->d(20)), CarbonImmutable::parse($this->d(22)))['total'];

        $this->assertSame($expected, $booking->total);
        $this->assertGreaterThan(100, $booking->total);
    }

    public function test_last_room_cannot_be_booked_twice(): void
    {
        $loft = StayUnit::where('name', 'Family Loft')->first();   // only 1
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $payload = ['stay_unit_id' => $loft->id, 'check_in' => $this->d(30), 'check_out' => $this->d(33), 'rooms' => 1, 'guests' => 3];

        $this->actingAs($alice)->post('/book/stay/matterhorn-view-chalet', $payload)->assertRedirectContains('/checkout/');
        // Bob tries while Alice's hold is live:
        $this->actingAs($bob)->post('/book/stay/matterhorn-view-chalet', $payload)
            ->assertRedirect('/stays/matterhorn-view-chalet')->assertSessionHasErrors(['booking' => 'Sorry — Family Loft was just booked for those dates.']);
        // Overlapping by one night also fails:
        $this->actingAs($bob)->post('/book/stay/matterhorn-view-chalet', ['check_in' => $this->d(32), 'check_out' => $this->d(34)] + $payload)->assertSessionHasErrors('booking');
        // Back-to-back works:
        $this->actingAs($bob)->post('/book/stay/matterhorn-view-chalet', ['check_in' => $this->d(33), 'check_out' => $this->d(35)] + $payload)->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Booking::where('stay_unit_id', $loft->id)->where('check_in', $this->d(30))->count());
    }

    public function test_expired_hold_releases_the_room_and_cannot_be_paid(): void
    {
        $loft = StayUnit::where('name', 'Family Loft')->first();
        $alice = $this->guest();
        $payload = ['stay_unit_id' => $loft->id, 'check_in' => $this->d(30), 'check_out' => $this->d(32), 'rooms' => 1, 'guests' => 2];
        $this->actingAs($alice)->post('/book/stay/matterhorn-view-chalet', $payload);
        $held = Booking::latest('id')->first();

        $this->travel(11)->minutes();

        $this->post(route('checkout.pay', $held), $this->details())->assertSessionHasErrors(['booking']);
        $this->assertNotSame(BookingStatus::Confirmed, $held->fresh()->status);

        $bob = User::factory()->customer()->create();
        $this->actingAs($bob)->post('/book/stay/matterhorn-view-chalet', $payload)->assertSessionDoesntHaveErrors();
    }

    public function test_double_click_creates_one_booking(): void
    {
        $user = $this->guest();
        $unit = StayUnit::where('name', 'Aurora Glass Cabin')->first();
        $payload = ['stay_unit_id' => $unit->id, 'check_in' => $this->d(20), 'check_out' => $this->d(22), 'rooms' => 1, 'guests' => 2, 'idempotency_key' => 'abc-123'];

        $this->actingAs($user)->post('/book/stay/black-sand-glass-cabins', $payload);
        $this->actingAs($user)->post('/book/stay/black-sand-glass-cabins', $payload);

        $this->assertSame(1, Booking::where('customer_id', $user->id)->count());
    }

    public function test_stay_rules_are_enforced(): void
    {
        $user = $this->guest();
        $double = StayUnit::where('name', 'Alpine Double')->first();
        $base = ['stay_unit_id' => $double->id, 'rooms' => 1, 'guests' => 2];

        $this->actingAs($user)->post('/book/stay/matterhorn-view-chalet', $base + ['check_in' => $this->d(10), 'check_out' => $this->d(11)])
            ->assertSessionHasErrors(['booking' => 'This stay takes bookings of 2–30 nights.']);
        $this->post('/book/stay/matterhorn-view-chalet', ['guests' => 5] + $base + ['check_in' => $this->d(10), 'check_out' => $this->d(12)])
            ->assertSessionHasErrors(['booking' => '1 × Alpine Double sleeps up to 2 guests.']);
        $this->post('/book/stay/matterhorn-view-chalet', ['rooms' => 9] + $base + ['check_in' => $this->d(10), 'check_out' => $this->d(12)])
            ->assertSessionHasErrors('booking');
        $this->post('/book/stay/matterhorn-view-chalet', $base + ['check_in' => $this->d(-3), 'check_out' => $this->d(2)])
            ->assertSessionHasErrors(['booking' => 'Check-in can’t be in the past.']);
        // a room type from another listing
        $other = StayUnit::where('name', 'Aurora Glass Cabin')->first();
        $this->post('/book/stay/matterhorn-view-chalet', ['stay_unit_id' => $other->id] + $base + ['check_in' => $this->d(10), 'check_out' => $this->d(12)])->assertNotFound();
        $this->assertSame(0, Booking::count());
    }

    public function test_restaurant_reservation_without_deposit_is_free_and_capacity_is_enforced(): void
    {
        $slot = $this->futureSlot('tide-toddy');   // 50 seats, no deposit
        $user = $this->guest();
        $this->actingAs($user)->post('/book/slot/tide-toddy', ['time_slot_id' => $slot->id, 'guests' => 4]);
        $booking = Booking::latest('id')->first();
        $this->get(route('checkout.show', $booking))->assertSee('Confirm reservation');
        $this->post(route('checkout.pay', $booking), $this->details(['special_requests' => 'Window table']));

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::NotRequired, $booking->payment_status);
        $this->assertSame(0, $booking->total);
        $this->assertSame(0, Payment::count());

        $this->bookSlot($slot, 44);   // 4 + 44 = 48 of 50
        $this->actingAs(User::factory()->customer()->create())->post('/book/slot/tide-toddy', ['time_slot_id' => $slot->id, 'guests' => 3])
            ->assertSessionHasErrors(['booking' => 'Sorry — only 2 seats left at this time.']);
    }

    public function test_dining_party_size_and_cutoff(): void
    {
        $user = $this->guest();
        $slot = $this->futureSlot('kamo-omakase-counter');   // parties up to 4
        $this->actingAs($user)->post('/book/slot/kamo-omakase-counter', ['time_slot_id' => $slot->id, 'guests' => 6])
            ->assertSessionHasErrors(['booking' => 'Online reservations are for parties of 1–4.']);

        $ski = Listing::where('slug', 'matterhorn-ski-day-with-a-guide')->first();   // 18h cut-off
        $today = CarbonImmutable::now($ski->destination->timezone)->toDateString();
        $soon = $ski->timeSlots()->whereDate('starts_at', $today)->first();
        $this->post('/book/slot/matterhorn-ski-day-with-a-guide', ['time_slot_id' => $soon->id, 'guests' => 1])
            ->assertSessionHasErrors(['booking' => 'Online booking for this time has closed. Please choose a later time.']);
    }

    public function test_activity_needs_participants_ages_and_safety_acknowledgement(): void
    {
        $user = $this->guest();
        $slot = $this->futureSlot('matterhorn-ski-day-with-a-guide');   // min age 12
        $this->actingAs($user)->post('/book/slot/matterhorn-ski-day-with-a-guide', ['time_slot_id' => $slot->id, 'guests' => 2]);
        $booking = Booking::latest('id')->first();

        $this->post(route('checkout.pay', $booking), $this->details(['participants' => [['full_name' => 'Asha', 'age' => 30]], 'acknowledged' => 1]))
            ->assertSessionHasErrors(['booking' => 'Please add the name and age of every participant.']);
        $this->post(route('checkout.pay', $booking), $this->details(['participants' => [['full_name' => 'Asha', 'age' => 30], ['full_name' => 'Kid', 'age' => 9]], 'acknowledged' => 1]))
            ->assertSessionHasErrors(['booking' => 'Every participant must be at least 12 years old for this activity.']);
        $this->post(route('checkout.pay', $booking), $this->details(['participants' => [['full_name' => 'Asha', 'age' => 30], ['full_name' => 'Ravi', 'age' => 33]]]))
            ->assertSessionHasErrors(['booking' => 'Please confirm that everyone meets the safety and eligibility requirements.']);
        $this->post(route('checkout.pay', $booking), $this->details(['participants' => [['full_name' => 'Asha', 'age' => 30], ['full_name' => 'Ravi', 'age' => 33]], 'acknowledged' => 1]));

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(2, $booking->participants()->count());
        $this->assertSame(1650000 * 2, $booking->total);
    }

    public function test_only_the_owner_can_open_or_pay_a_checkout(): void
    {
        $user = $this->guest();
        $slot = $this->futureSlot('tide-toddy');
        $this->actingAs($user)->post('/book/slot/tide-toddy', ['time_slot_id' => $slot->id, 'guests' => 2]);
        $booking = Booking::latest('id')->first();

        $intruder = User::factory()->customer()->create();
        $this->actingAs($intruder)->get(route('checkout.show', $booking))->assertNotFound();
        $this->actingAs($intruder)->post(route('checkout.pay', $booking), $this->details())->assertNotFound();
        $this->actingAs(User::factory()->provider()->create())->post('/book/slot/tide-toddy', ['time_slot_id' => $slot->id, 'guests' => 2])->assertForbidden();
        $this->post('/logout');
        $this->post('/book/slot/tide-toddy', ['time_slot_id' => $slot->id, 'guests' => 2])->assertRedirect(route('login'));
    }

    public function test_sign_in_to_book_returns_to_the_listing(): void
    {
        $this->get('/login?next=/stays/matterhorn-view-chalet');
        $this->post('/demo-login/customer')->assertRedirect('/stays/matterhorn-view-chalet');

        $this->post('/logout');
        $this->get('/login?next=//evil.example.com');
        $this->post('/demo-login/customer')->assertRedirect(route('dashboard'));
    }
}
