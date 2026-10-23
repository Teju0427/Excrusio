<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\StayUnit;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\BookingMaintenance;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistAndTripTest extends TestCase
{
    use RefreshDatabase;

    private function kamoSlot(int $days = 6)
    {
        $listing = Listing::where('slug', 'kamo-omakase-counter')->first();

        return $listing->timeSlots()->where('starts_at', '>', CarbonImmutable::now($listing->destination->timezone)->addDays($days)->format('Y-m-d H:i:s'))->orderBy('starts_at')->first();
    }

    private function fill($slot, int $guests): Booking
    {
        $svc = app(BookingService::class);
        $u = User::factory()->customer()->create();

        return $svc->checkout($svc->holdSlot($u, $slot, $guests), ['guest_name' => $u->name, 'guest_email' => $u->email]);
    }

    public function test_waitlist_offer_hold_and_claim(): void
    {
        $slot = $this->kamoSlot();   // 10 seats
        $a = $this->fill($slot, 4);
        $this->fill($slot, 4);
        $this->fill($slot, 2);

        $first = User::factory()->customer()->create();
        $second = User::factory()->customer()->create();
        $this->actingAs($first)->post(route('waitlist.join', $slot), ['guests' => 3])->assertSessionHas('status');
        $this->actingAs($second)->post(route('waitlist.join', $slot), ['guests' => 2]);

        // 4 seats free up → first in line (3) gets an offer; the 1 left isn't enough for #2.
        $this->actingAs($a->customer)->post(route('bookings.cancel', $a))->assertRedirect()->assertSessionHas('status');
        $offer = WaitlistEntry::where('user_id', $first->id)->first();
        $this->assertSame('offered', $offer->status);
        $this->assertSame('waiting', WaitlistEntry::where('user_id', $second->id)->first()->status);
        $this->assertSame(1, $first->notifications()->count());

        // The offered seats are held: a stranger can't take them.
        $this->actingAs(User::factory()->customer()->create())->post('/book/slot/kamo-omakase-counter', ['time_slot_id' => $slot->id, 'guests' => 2])
            ->assertSessionHasErrors('booking');

        $this->actingAs($first)->get(route('waitlist.claim', $offer))->assertRedirectContains('/checkout/');
        $this->assertSame('claimed', $offer->fresh()->status);
        $this->assertSame(3, Booking::where('customer_id', $first->id)->first()->guests);
    }

    public function test_unclaimed_offer_passes_to_the_next_person(): void
    {
        $slot = $this->kamoSlot();
        $a = $this->fill($slot, 4);
        $this->fill($slot, 4);
        $this->fill($slot, 2);
        $first = User::factory()->customer()->create();
        $second = User::factory()->customer()->create();
        $this->actingAs($first)->post(route('waitlist.join', $slot), ['guests' => 4]);
        $this->actingAs($second)->post(route('waitlist.join', $slot), ['guests' => 4]);
        $this->actingAs($a->customer)->post(route('bookings.cancel', $a));

        $this->travel(31)->minutes();
        app(BookingMaintenance::class)->run();

        $this->assertSame('expired', WaitlistEntry::where('user_id', $first->id)->first()->status);
        $this->assertSame('offered', WaitlistEntry::where('user_id', $second->id)->first()->status);
        $this->actingAs($first)->get(route('waitlist.claim', WaitlistEntry::where('user_id', $first->id)->first()))->assertSessionHasErrors('booking');
    }

    public function test_cannot_join_waitlist_when_there_is_space(): void
    {
        $slot = $this->kamoSlot();
        $this->actingAs(User::factory()->customer()->create())->post(route('waitlist.join', $slot), ['guests' => 2])
            ->assertSessionHasErrors(['booking' => 'Good news — there is space at this time, so you can book it directly.']);
    }

    private function tripDates(): array
    {
        // A Wednesday at least a week away, so the omakase counter and tea ceremony are both open.
        $d = CarbonImmutable::now('Asia/Tokyo')->addDays(8);
        while ($d->dayOfWeek !== 3) {
            $d = $d->addDay();
        }

        return [$d, Listing::where('slug', 'higashiyama-garden-ryokan')->first(), Listing::where('slug', 'kamo-omakase-counter')->first(), Listing::where('slug', 'tea-ceremony-in-a-machiya')->first()];
    }

    public function test_trip_cart_books_everything_in_one_payment(): void
    {
        [$day, $ryokan, $kamo, $tea] = $this->tripDates();
        $user = User::factory()->customer()->create();
        $room = StayUnit::where('name', 'Garden Tatami Room')->first();
        $dinner = $kamo->timeSlots()->where('starts_at', $day->toDateString().' 19:00:00')->first();
        $teaSlot = $tea->timeSlots()->where('starts_at', $day->toDateString().' 10:00:00')->first();

        $this->actingAs($user)->post(route('trip.add-stay', $ryokan), ['stay_unit_id' => $room->id, 'check_in' => $day->toDateString(), 'check_out' => $day->addDays(2)->toDateString(), 'rooms' => 1, 'guests' => 2])->assertRedirect(route('trip.index'));
        $this->post(route('trip.add-slot', $kamo), ['time_slot_id' => $dinner->id, 'guests' => 2]);
        $this->post(route('trip.add-slot', $tea), ['time_slot_id' => $teaSlot->id, 'guests' => 2]);
        $this->get(route('trip.index'))->assertOk()->assertSee('Higashiyama Garden Ryokan')->assertSee('Book this trip')->assertDontSee('Please fix these timings');

        $this->post(route('trip.hold'))->assertRedirectContains('/trip/');
        $group = BookingGroup::first();
        $this->assertSame(3, $group->bookings()->count());

        $teaBooking = $group->bookings()->where('time_slot_id', $teaSlot->id)->first();
        $this->get(route('trip.checkout', $group))->assertOk()->assertSee('One demo payment for the whole trip');
        $this->post(route('trip.pay', $group), [
            'guest_name' => $user->name, 'guest_email' => $user->email,
            'participants' => [$teaBooking->id => [['full_name' => 'A', 'age' => 30], ['full_name' => 'B', 'age' => 31]]],
            'acknowledged' => [$teaBooking->id => 1],
        ])->assertRedirect(route('trip.show', $group));

        $group->refresh();
        $this->assertSame((int) $group->bookings()->sum('total'), $group->total);
        $this->assertSame(1, Payment::where('booking_group_id', $group->id)->where('type', 'charge')->count());
        $statuses = $group->bookings()->pluck('status')->map->value->sort()->values()->all();
        $this->assertSame(['confirmed', 'confirmed', 'pending'], $statuses);   // the ryokan is request-to-book
        $this->assertSame([], session('trip_cart', []));
    }

    public function test_trip_clashes_are_detected(): void
    {
        [$day, $ryokan, $kamo, $tea] = $this->tripDates();
        $user = User::factory()->customer()->create();
        $room = StayUnit::where('name', 'Garden Tatami Room')->first();
        $this->actingAs($user)->post(route('trip.add-stay', $ryokan), ['stay_unit_id' => $room->id, 'check_in' => $day->toDateString(), 'check_out' => $day->addDay()->toDateString(), 'rooms' => 1, 'guests' => 2]);
        // dinner three days later: outside the 1-night stay in the same city
        $late = $kamo->timeSlots()->where('starts_at', $day->addDays(3)->toDateString().' 19:00:00')->first();
        $this->post(route('trip.add-slot', $kamo), ['time_slot_id' => $late->id, 'guests' => 2]);

        $this->get(route('trip.index'))->assertSee('is outside your stay at Higashiyama Garden Ryokan');
        $this->post(route('trip.hold'))->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
    }

    public function test_overlapping_sessions_clash(): void
    {
        [$day, , $kamo] = $this->tripDates();
        $user = User::factory()->customer()->create();
        $a = $kamo->timeSlots()->where('starts_at', $day->toDateString().' 19:00:00')->first();
        $b = $kamo->timeSlots()->where('starts_at', $day->toDateString().' 19:30:00')->first();
        $this->actingAs($user)->post(route('trip.add-slot', $kamo), ['time_slot_id' => $a->id, 'guests' => 2]);
        $this->post(route('trip.add-slot', $kamo), ['time_slot_id' => $b->id, 'guests' => 2]);

        $this->get(route('trip.index'))->assertSee('overlaps with');
    }

    public function test_trip_is_all_or_nothing(): void
    {
        [$day, $ryokan, $kamo] = $this->tripDates();
        $user = User::factory()->customer()->create();
        $room = StayUnit::where('name', 'Garden Tatami Room')->first();
        $dinner = $kamo->timeSlots()->where('starts_at', $day->toDateString().' 19:00:00')->first();
        $this->actingAs($user)->post(route('trip.add-stay', $ryokan), ['stay_unit_id' => $room->id, 'check_in' => $day->toDateString(), 'check_out' => $day->addDays(2)->toDateString(), 'rooms' => 1, 'guests' => 2]);
        $this->post(route('trip.add-slot', $kamo), ['time_slot_id' => $dinner->id, 'guests' => 2]);

        $this->fill($dinner, 4);   // others fill the 10-seat counter meanwhile
        $this->fill($dinner, 4);
        $this->fill($dinner, 2);

        $this->actingAs($user)->post(route('trip.hold'))->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::where('customer_id', $user->id)->count(), 'the room must not be booked if the dinner fails');
        $this->assertSame(0, BookingGroup::count());
    }
}
