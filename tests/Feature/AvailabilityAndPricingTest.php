<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\AvailabilityBlock;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class AvailabilityAndPricingTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function unit(string $name): StayUnit
    {
        return StayUnit::where('name', $name)->firstOrFail();   // e.g. "Family Loft" has quantity 1
    }

    private function d(int $days): string
    {
        return CarbonImmutable::now()->addDays($days)->toDateString();
    }

    public function test_overlapping_stays_reduce_rooms_but_back_to_back_stays_do_not(): void
    {
        $svc = app(AvailabilityService::class);
        $loft = $this->unit('Family Loft');   // 1 room
        $this->bookStay($loft, $this->d(10), $this->d(13));

        $this->assertSame(0, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(11)), CarbonImmutable::parse($this->d(12))));
        $this->assertSame(0, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(8)), CarbonImmutable::parse($this->d(11))));
        // Check-out day of one guest is the check-in day of the next: allowed.
        $this->assertSame(1, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(13)), CarbonImmutable::parse($this->d(15))));
        $this->assertSame(1, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(7)), CarbonImmutable::parse($this->d(10))));
    }

    public function test_rooms_left_uses_the_busiest_night(): void
    {
        $svc = app(AvailabilityService::class);
        $double = $this->unit('Alpine Double');   // 4 rooms
        $this->bookStay($double, $this->d(10), $this->d(12), rooms: 2);
        $this->bookStay($double, $this->d(11), $this->d(14), rooms: 1);

        $this->assertSame(1, $svc->roomsLeft($double, CarbonImmutable::parse($this->d(10)), CarbonImmutable::parse($this->d(14))));
        $this->assertSame(3, $svc->roomsLeft($double, CarbonImmutable::parse($this->d(12)), CarbonImmutable::parse($this->d(14))));
    }

    public function test_cancelled_and_expired_bookings_free_their_rooms(): void
    {
        $svc = app(AvailabilityService::class);
        $loft = $this->unit('Family Loft');
        $this->bookStay($loft, $this->d(10), $this->d(12), status: BookingStatus::Cancelled);
        $this->bookStay($loft, $this->d(10), $this->d(12), status: BookingStatus::AwaitingPayment, extra: ['hold_expires_at' => now()->subMinute()]);

        $this->assertSame(1, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(10)), CarbonImmutable::parse($this->d(12))));

        // A pending hotel request and a live payment hold both keep the room.
        $this->bookStay($loft, $this->d(10), $this->d(12), status: BookingStatus::AwaitingPayment, extra: ['hold_expires_at' => now()->addMinutes(5)]);
        $this->assertSame(0, $svc->roomsLeft($loft, CarbonImmutable::parse($this->d(10)), CarbonImmutable::parse($this->d(12))));
    }

    public function test_host_closures_block_rooms(): void
    {
        $svc = app(AvailabilityService::class);
        $double = $this->unit('Alpine Double');
        AvailabilityBlock::create(['listing_id' => $double->listing_id, 'stay_unit_id' => $double->id, 'start_date' => $this->d(20), 'end_date' => $this->d(22), 'rooms' => 3]);
        AvailabilityBlock::create(['listing_id' => $double->listing_id, 'stay_unit_id' => null, 'start_date' => $this->d(30), 'end_date' => $this->d(31)]);

        $this->assertSame(1, $svc->roomsLeft($double, CarbonImmutable::parse($this->d(20)), CarbonImmutable::parse($this->d(21))));
        $this->assertSame(0, $svc->roomsLeft($double, CarbonImmutable::parse($this->d(30)), CarbonImmutable::parse($this->d(31))));
    }

    public function test_slot_remaining_capacity(): void
    {
        $svc = app(AvailabilityService::class);
        $slot = Listing::where('slug', 'kamo-omakase-counter')->first()->timeSlots()->where('starts_at', '>', now()->addDays(3))->first();   // 10 seats
        $this->bookSlot($slot, 4);
        $this->bookSlot($slot, 3);
        $this->bookSlot($slot, 5, BookingStatus::Cancelled);

        $this->assertSame(3, $svc->slotRemaining($slot));
    }

    public function test_slots_inside_the_booking_cutoff_are_hidden(): void
    {
        $listing = Listing::where('slug', 'matterhorn-ski-day-with-a-guide')->first();   // 18 h cut-off, 09:00 daily
        $tz = $listing->destination->timezone;
        $today = CarbonImmutable::now($tz)->toDateString();

        // Today's 09:00 session is always less than 18 h away (or already past).
        $this->assertCount(0, app(AvailabilityService::class)->slotsOn($listing, $today));
        $this->assertCount(1, app(AvailabilityService::class)->slotsOn($listing, CarbonImmutable::now($tz)->addDays(3)->toDateString()));
    }

    public function test_weekend_price_rule_applies_to_friday_and_saturday_nights(): void
    {
        $unit = $this->unit('Alpine Double');   // ₹38,500, weekend +15%
        $friday = CarbonImmutable::now()->next(CarbonImmutable::FRIDAY);
        $quote = app(PricingService::class)->quoteStay($unit, $friday->subDay(), $friday->addDays(3), rooms: 2);   // Thu, Fri, Sat, Sun nights

        $prices = array_column($quote['nights'], 'price');
        $this->assertSame([3850000, 4427500, 4427500, 3850000], $prices);
        $this->assertSame(4, $quote['nights_count']);
        $this->assertSame(array_sum($prices) * 2, $quote['subtotal']);
        $this->assertSame($quote['subtotal'], $quote['total']);   // no fee by default
    }

    public function test_service_fee_is_added_when_configured(): void
    {
        config(['excursio.service_fee_percent' => 5]);
        $slot = Listing::where('slug', 'tea-ceremony-in-a-machiya')->first()->timeSlots()->first();
        $quote = app(PricingService::class)->quoteSlot($slot->listing, $slot, 3);

        $this->assertSame(480000 * 3, $quote['subtotal']);
        $this->assertSame((int) round(480000 * 3 * 0.05), $quote['fees']);
    }

    public function test_dining_without_deposit_costs_nothing_online(): void
    {
        $slot = Listing::where('slug', 'tide-toddy')->first()->timeSlots()->first();
        $this->assertSame(0, app(PricingService::class)->quoteSlot($slot->listing, $slot, 4)['total']);
    }

    public function test_stay_availability_endpoint(): void
    {
        $loft = $this->unit('Family Loft');
        $this->bookStay($loft, $this->d(10), $this->d(13));

        $res = $this->getJson('/api/listings/matterhorn-view-chalet/stay?check_in='.$this->d(11).'&check_out='.$this->d(13).'&guests=2')->assertOk();
        $units = collect($res->json('units'))->keyBy('name');

        $this->assertFalse($units['Family Loft']['available']);
        $this->assertSame('Sold out for these dates', $units['Family Loft']['reason']);
        $this->assertTrue($units['Alpine Double']['available']);
        $this->assertSame(4, $units['Alpine Double']['rooms_left']);
        $this->assertCount(2, $units['Alpine Double']['quote']['nights']);
    }

    public function test_stay_endpoint_validates_dates_and_minimum_nights(): void
    {
        $this->getJson('/api/listings/matterhorn-view-chalet/stay?check_in='.$this->d(5).'&check_out='.$this->d(4).'&guests=2')
            ->assertStatus(422)->assertJsonValidationErrors('check_out');
        $this->getJson('/api/listings/matterhorn-view-chalet/stay?check_in='.$this->d(-2).'&check_out='.$this->d(4).'&guests=2')
            ->assertStatus(422)->assertJsonValidationErrors('check_in');
        // The chalet has a 2-night minimum.
        $this->getJson('/api/listings/matterhorn-view-chalet/stay?check_in='.$this->d(5).'&check_out='.$this->d(6).'&guests=2')
            ->assertStatus(422)->assertJsonFragment(['message' => 'This stay takes bookings of 2–30 nights.']);
        // Guests too many for one room of a type → that type is not available.
        $units = collect($this->getJson('/api/listings/matterhorn-view-chalet/stay?check_in='.$this->d(5).'&check_out='.$this->d(7).'&guests=5')->json('units'))->keyBy('name');
        $this->assertFalse($units['Alpine Double']['available']);
        $this->assertTrue($units['Family Loft']['available']);
    }

    public function test_slots_endpoint_shows_remaining_places(): void
    {
        $listing = Listing::where('slug', 'kamo-omakase-counter')->first();
        $tz = $listing->destination->timezone;
        $slot = $listing->timeSlots()->where('starts_at', '>', CarbonImmutable::now($tz)->addDays(2)->format('Y-m-d H:i:s'))->orderBy('starts_at')->first();
        $this->bookSlot($slot, 9);

        $res = $this->getJson('/api/listings/kamo-omakase-counter/slots?date='.$slot->starts_at->toDateString().'&guests=2')->assertOk();
        $row = collect($res->json('slots'))->firstWhere('id', $slot->id);

        $this->assertSame(1, $row['remaining']);
        $this->assertFalse($row['available']);
        $this->getJson('/api/listings/kamo-omakase-counter/slots?date='.$slot->starts_at->toDateString().'&guests=9')
            ->assertStatus(422);   // counter takes parties of up to 4
    }

    public function test_slot_endpoint_does_not_work_for_stays_or_drafts(): void
    {
        $this->getJson('/api/listings/matterhorn-view-chalet/slots?date='.$this->d(3).'&guests=2')->assertNotFound();
        Listing::where('slug', 'kamo-omakase-counter')->first()->forceFill(['status' => 'draft'])->save();
        $this->getJson('/api/listings/kamo-omakase-counter/slots?date='.$this->d(3).'&guests=2')->assertNotFound();
    }
}
