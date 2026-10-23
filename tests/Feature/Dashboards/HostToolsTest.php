<?php

namespace Tests\Feature\Dashboards;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Support\VenueClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class HostToolsTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function host(string $who = 'alpine'): User
    {
        return User::where('email', "host.{$who}@excursio.test")->first();
    }

    public function test_host_dashboard_shows_kpis_and_charts(): void
    {
        $this->bookStay(StayUnit::where('name', 'Alpine Double')->first(), CarbonImmutable::today()->addDays(5)->toDateString(), CarbonImmutable::today()->addDays(6)->toDateString());
        $this->actingAs($this->host())->get('/host')->assertOk()
            ->assertSee('Requests to answer')->assertSee('Room occupancy, next 30 days')
            ->assertSee('data-chart-for="host-trend"', false)->assertSee('vendor/chartjs/chart.umd.min.js')
            ->assertSee('View as table')->assertSee('Matterhorn View Chalet');
    }

    public function test_occupancy_is_calculated_from_bookings(): void
    {
        // Lake Pichola Haveli: 6 + 2 rooms. Book 4 rooms for 15 of the next 30 nights → 60 / 240 = 25%.
        $host = $this->host();
        $haveli = Listing::where('slug', 'lake-pichola-haveli')->first();
        $others = $host->listings()->where('listing_type', 'stay')->where('id', '!=', $haveli->id)->pluck('id');
        \App\Models\StayUnit::whereIn('listing_id', $others)->update(['is_active' => false]);
        $unit = $haveli->stayUnits()->where('name', 'Heritage Room')->first();
        $this->bookStay($unit, CarbonImmutable::today()->toDateString(), CarbonImmutable::today()->addDays(15)->toDateString(), 4);

        $this->assertSame(25, app(\App\Services\AnalyticsService::class)->stayOccupancy($host->listings()->pluck('id'), 30));
    }

    public function test_hosts_can_only_manage_their_own_listings(): void
    {
        $kansai = $this->host('kansai');
        $this->actingAs($kansai)->get('/host/listings/matterhorn-view-chalet/availability')->assertNotFound();
        $this->actingAs($kansai)->get('/host/listings/matterhorn-view-chalet/edit')->assertNotFound();
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $this->actingAs($kansai)->put("/host/listings/matterhorn-view-chalet/units/{$unit->id}", ['name' => 'Hacked', 'price' => 1, 'quantity' => 0, 'max_guests' => 1])->assertNotFound();
        $this->assertNotSame('Hacked', $unit->fresh()->name);
        $this->actingAs(User::factory()->customer()->create())->get('/host/calendar')->assertForbidden();
    }

    public function test_room_count_cannot_drop_below_rooms_already_booked(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();   // 4 rooms
        $in = CarbonImmutable::today()->addDays(20);
        $booking = $this->bookStay($unit, $in->toDateString(), $in->addDays(2)->toDateString(), 3, BookingStatus::Confirmed, ['unit_price' => 1500000, 'total' => 3000000]);

        $this->actingAs($this->host())->put("/host/listings/matterhorn-view-chalet/units/{$unit->id}", ['name' => 'Alpine Double', 'price' => 99999, 'quantity' => 2, 'max_guests' => 2, 'is_active' => 1])
            ->assertSessionHasErrors('booking');
        $this->assertSame(4, $unit->fresh()->quantity);

        $this->actingAs($this->host())->put("/host/listings/matterhorn-view-chalet/units/{$unit->id}", ['name' => 'Alpine Double', 'price' => 20000, 'quantity' => 3, 'max_guests' => 2, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(2000000, $unit->fresh()->price_per_night);
        // The existing guest keeps the price they booked.
        $this->assertSame(3000000, $booking->fresh()->total);
    }

    public function test_closed_dates_stop_new_bookings_but_keep_existing_ones(): void
    {
        $unit = StayUnit::where('name', 'Matterhorn Suite')->first();   // 2 rooms
        $in = CarbonImmutable::today()->addDays(30);
        $existing = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString());

        $this->actingAs($this->host())->post('/host/listings/matterhorn-view-chalet/blocks', [
            'stay_unit_id' => $unit->id, 'start_date' => $in->toDateString(), 'end_date' => $in->addDays(3)->toDateString(), 'reason' => 'Renovation',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, '1 existing booking'));

        $this->assertSame(0, app(AvailabilityService::class)->roomsLeft($unit, $in, $in->addDays(2)));
        $this->assertSame(BookingStatus::Confirmed, $existing->fresh()->status);

        $this->expectException(BookingException::class);
        app(BookingService::class)->holdStay(User::factory()->customer()->create(), $unit, $in->toDateString(), $in->addDay()->toDateString(), 1, 2);
    }

    public function test_block_in_the_past_or_on_someone_elses_room_is_refused(): void
    {
        $foreign = StayUnit::where('name', 'Garden Tatami Room')->first();
        $this->actingAs($this->host())->post('/host/listings/matterhorn-view-chalet/blocks', [
            'start_date' => CarbonImmutable::today()->subDays(3)->toDateString(), 'end_date' => CarbonImmutable::today()->toDateString(),
        ])->assertSessionHasErrors('start_date');
        $this->actingAs($this->host())->post('/host/listings/matterhorn-view-chalet/blocks', [
            'stay_unit_id' => $foreign->id, 'start_date' => CarbonImmutable::today()->addDay()->toDateString(), 'end_date' => CarbonImmutable::today()->addDays(2)->toDateString(),
        ])->assertSessionHasErrors('booking');
        $this->assertSame(0, \App\Models\AvailabilityBlock::count());
    }

    public function test_saving_a_schedule_rebuilds_sessions_but_keeps_booked_ones(): void
    {
        $listing = Listing::where('slug', 'osteria-del-cipresso')->first();
        $booked = $listing->timeSlots()->where('starts_at', '>', now()->addDays(2))->orderBy('starts_at')->first();
        $this->bookSlot($booked, 2);
        $bookedAt = $booked->starts_at->format('Y-m-d H:i:s');

        // New rule: Mondays only, 12:00–14:00 every hour, 1 hour, 10 seats.
        $this->actingAs($this->host())->put('/host/listings/osteria-del-cipresso/schedules', ['rules' => [
            ['weekday' => 1, 'start_time' => '12:00', 'end_time' => '14:00', 'interval_minutes' => 60, 'slot_length_minutes' => 60, 'capacity' => 10],
        ]])->assertSessionHasNoErrors()->assertSessionHas('status');

        $future = $listing->timeSlots()->where('starts_at', '>', VenueClock::now($listing)->format('Y-m-d H:i:s'))->get();
        $this->assertTrue($future->contains(fn ($s) => $s->starts_at->format('Y-m-d H:i:s') === $bookedAt), 'Booked session must be kept');
        $others = $future->reject(fn ($s) => $s->id === $booked->id);
        $this->assertNotEmpty($others);
        $this->assertTrue($others->every(fn ($s) => $s->starts_at->dayOfWeek === 1 && $s->capacity === 10));
    }

    public function test_closed_session_cannot_be_booked(): void
    {
        $slot = Listing::where('slug', 'old-city-heritage-walk')->first()->timeSlots()->where('starts_at', '>', now()->addDays(2))->first();
        $this->actingAs($this->host())->patch("/host/slots/{$slot->id}/status", ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $slot->fresh()->status);

        $this->expectException(BookingException::class);
        app(BookingService::class)->holdSlot(User::factory()->customer()->create(), $slot->fresh(), 1);
    }

    public function test_qr_check_in_by_the_listings_host(): void
    {
        $listing = Listing::where('slug', 'matterhorn-view-chalet')->first();
        $unit = $listing->stayUnits()->first();
        $now = VenueClock::now($listing);
        $token = str_repeat('Q', 40);
        $b = $this->bookStay($unit, $now->toDateString(), $now->addDay()->toDateString(), 1, BookingStatus::Confirmed, [
            'checkin_token' => $token, 'starts_at' => $now->addHour()->format('Y-m-d H:i:s'), 'ends_at' => $now->addDay()->format('Y-m-d H:i:s'),
        ]);

        // Another host, or the guest, can't even see it.
        $this->actingAs($this->host('kansai'))->get("/host/check-in/{$token}")->assertNotFound();
        $this->actingAs($b->customer)->get("/host/check-in/{$token}")->assertForbidden();

        $host = $this->host();
        $this->actingAs($host)->post('/host/check-in', ['code' => strtolower($b->reference)])->assertRedirect("/host/check-in/{$token}");
        $this->actingAs($host)->post('/host/check-in', ['code' => 'http://localhost/host/check-in/'.$token])->assertRedirect("/host/check-in/{$token}");
        $this->actingAs($host)->get("/host/check-in/{$token}")->assertOk()->assertSee('Check in guest');
        $this->actingAs($host)->post("/host/check-in/{$token}")->assertSessionHas('status', fn ($s) => str_contains($s, 'is checked in'));
        $this->assertNotNull($b->fresh()->checked_in_at);
        $this->assertSame($host->id, $b->fresh()->checked_in_by);
        // Scanning twice is harmless.
        $this->actingAs($host)->post("/host/check-in/{$token}")->assertSessionHas('status', fn ($s) => str_contains($s, 'already'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.checked_in', 'auditable_id' => $b->id]);
    }

    public function test_check_in_is_refused_too_early_or_when_not_confirmed(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(5);
        $early = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString(), 1, BookingStatus::Confirmed, ['checkin_token' => str_repeat('E', 40)]);
        $cancelled = $this->bookStay($unit, CarbonImmutable::today()->toDateString(), CarbonImmutable::today()->addDay()->toDateString(), 1, BookingStatus::Cancelled, ['checkin_token' => str_repeat('C', 40)]);

        $this->actingAs($this->host())->post('/host/check-in/'.str_repeat('E', 40))->assertSessionHasErrors('booking');
        $this->actingAs($this->host())->post('/host/check-in/'.str_repeat('C', 40))->assertSessionHasErrors('booking');
        $this->assertNull($early->fresh()->checked_in_at);
        $this->assertNull($cancelled->fresh()->checked_in_at);
        $this->actingAs($this->host())->post('/host/check-in', ['code' => 'EXC-NOPE'])->assertSessionHasErrors('code');
    }

    public function test_calendar_shows_rooms_booked_per_day(): void
    {
        $unit = StayUnit::where('name', 'Heritage Room')->first();   // Lake Pichola Haveli, 6 + 2 rooms
        $day = CarbonImmutable::today()->addMonth()->startOfMonth()->addDays(9);
        $this->bookStay($unit, $day->toDateString(), $day->addDays(2)->toDateString(), 3);

        $this->actingAs($this->host())->get('/host/calendar?listing=lake-pichola-haveli&month='.$day->format('Y-m').'&day='.$day->toDateString())
            ->assertOk()->assertSee($day->format('l j F').': 3 of 8 rooms booked')->assertSee('3/8 rooms');
        $this->actingAs($this->host())->get('/host/calendar?month=garbage')->assertOk();
    }

    public function test_new_listing_goes_to_review_and_is_hidden_until_approved(): void
    {
        $host = $this->host();
        $category = \App\Models\Category::where('listing_type', 'activity')->first();
        $destination = \App\Models\Destination::where('name', 'Tuscany')->first();

        $this->actingAs($host)->get('/host/listings/new?type=activity')->assertOk()->assertSee('When you’re open');
        $this->actingAs($host)->post('/host/listings', [
            'listing_type' => 'activity', 'title' => 'Truffle Hunt with Lupo', 'category_id' => $category->id, 'destination_id' => $destination->id,
            'summary' => 'Walk the oak woods with a truffle dog and his handler.',
            'description' => str_repeat('A morning in the woods looking for truffles with a trained dog. ', 3),
            'price' => 4500, 'booking_mode' => 'instant', 'cancellation_policy' => 'moderate',
            'duration_minutes' => 150, 'booking_cutoff_hours' => 12,
            'weekdays' => [6], 'start_time' => '09:00', 'end_time' => '12:00', 'interval_minutes' => 180, 'slot_length_minutes' => 150, 'capacity' => 6,
        ])->assertRedirect('/host/listings/truffle-hunt-with-lupo/edit');

        $listing = Listing::where('slug', 'truffle-hunt-with-lupo')->first();
        $this->assertSame('pending_review', $listing->status->value);
        $this->assertSame(450000, $listing->base_price);
        $this->assertGreaterThan(0, $listing->timeSlots()->count());
        $this->assertFalse($listing->is_illustrative);
        $this->actingAs($host)->get('/experiences/truffle-hunt-with-lupo')->assertOk();   // the host can preview it
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/experiences/truffle-hunt-with-lupo')->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get('/experiences/truffle-hunt-with-lupo')->assertNotFound();

        $admin = User::where('email', 'admin@excursio.test')->first();
        $this->actingAs($admin)->get('/admin/listings')->assertOk()->assertSee('Truffle Hunt with Lupo');
        $this->actingAs($admin)->patch('/admin/listings/truffle-hunt-with-lupo', ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch('/admin/listings/truffle-hunt-with-lupo', ['decision' => 'approve'])->assertSessionHasNoErrors();

        $this->assertSame('published', $listing->fresh()->status->value);
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/experiences/truffle-hunt-with-lupo')->assertOk();
        $this->assertTrue($host->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'approved')));
    }

    public function test_listing_validation_and_prices_in_rupees(): void
    {
        $this->actingAs($this->host())->post('/host/listings', ['listing_type' => 'stay', 'title' => 'x', 'price' => -5, 'category_id' => 999])
            ->assertSessionHasErrors(['title', 'price', 'category_id', 'summary', 'unit_name']);
        // A dining category can't be used for a stay.
        $dining = \App\Models\Category::where('listing_type', 'dining')->first();
        $this->actingAs($this->host())->post('/host/listings', ['listing_type' => 'stay', 'category_id' => $dining->id])->assertSessionHasErrors('category_id');

        $this->actingAs($this->host())->put('/host/listings/osteria-del-cipresso', [
            'title' => 'Osteria del Cipresso', 'category_id' => Listing::where('slug', 'osteria-del-cipresso')->value('category_id'),
            'destination_id' => Listing::where('slug', 'osteria-del-cipresso')->value('destination_id'),
            'summary' => 'Tuscan cooking under a cypress-lined terrace.', 'description' => str_repeat('Handmade pasta and long lunches. ', 3),
            'price' => 2200, 'booking_mode' => 'instant', 'cancellation_policy' => 'flexible',
            'cuisine' => 'Tuscan', 'price_band' => 3, 'max_party_size' => 8, 'deposit' => 500,
        ])->assertSessionHasNoErrors();
        $this->assertSame(50000, Listing::where('slug', 'osteria-del-cipresso')->first()->diningDetail->deposit_per_guest);
        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.updated']);
    }
}
