<?php

namespace Tests\Feature\Dashboards;

use App\Enums\BookingStatus;
use App\Models\Listing;
use App\Models\StayUnit;
use App\Models\User;
use App\Notifications\SimpleNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    public function test_bookings_are_grouped_into_tabs(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $soon = CarbonImmutable::today()->addDays(10);
        $upcoming = $this->bookStay($unit, $soon->toDateString(), $soon->addDays(2)->toDateString());
        $guest = $upcoming->customer;
        $past = $this->bookStay($unit, $soon->subDays(40)->toDateString(), $soon->subDays(38)->toDateString(), 1, BookingStatus::Completed, ['customer_id' => $guest->id]);
        $cancelled = $this->bookStay($unit, $soon->addDays(5)->toDateString(), $soon->addDays(6)->toDateString(), 1, BookingStatus::Cancelled, ['customer_id' => $guest->id]);
        $pending = $this->bookStay($unit, $soon->addDays(8)->toDateString(), $soon->addDays(9)->toDateString(), 1, BookingStatus::Pending, ['customer_id' => $guest->id]);
        $abandoned = $this->bookStay($unit, $soon->addDays(3)->toDateString(), $soon->addDays(4)->toDateString(), 1, BookingStatus::AwaitingPayment, ['customer_id' => $guest->id]);

        $this->actingAs($guest)->get('/bookings')->assertOk()
            ->assertSee($upcoming->reference)->assertDontSee($past->reference)->assertDontSee($cancelled->reference)->assertDontSee($abandoned->reference);
        $this->actingAs($guest)->get('/bookings?tab=past')->assertSee($past->reference)->assertDontSee($upcoming->reference);
        $this->actingAs($guest)->get('/bookings?tab=cancelled')->assertSee($cancelled->reference);
        $this->actingAs($guest)->get('/bookings?tab=pending')->assertSee($pending->reference);
        // An unknown tab falls back to upcoming instead of erroring.
        $this->actingAs($guest)->get('/bookings?tab=../../etc')->assertOk()->assertSee($upcoming->reference);
    }

    public function test_dashboard_shows_next_bookings_and_notifications(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(4);
        $b = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString());
        $b->customer->notify(new SimpleNotification('Your table is ready', '/bookings/'.$b->reference));

        $this->actingAs($b->customer)->get('/account')->assertOk()
            ->assertSee('Coming up')->assertSee('Matterhorn View Chalet')->assertSee('Your table is ready');
    }

    public function test_notifications_can_be_opened_and_marked_read(): void
    {
        $user = User::factory()->customer()->create();
        $user->notify(new SimpleNotification('Hello there', '/account/wishlist'));
        $user->notify(new SimpleNotification('Sneaky', 'https://evil.example/phish'));
        $all = $user->notifications()->get();
        $hello = $all->first(fn ($n) => $n->data['message'] === 'Hello there');
        $sneaky = $all->first(fn ($n) => $n->data['message'] === 'Sneaky');

        $this->actingAs($user)->get('/')->assertSee('Notifications, 2 unread');
        $this->actingAs($user)->get('/notifications/'.$hello->id)->assertRedirect('/account/wishlist');
        // External links are never followed (no open redirect).
        $this->actingAs($user)->get('/notifications/'.$sneaky->id)->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->unreadNotifications()->count());

        $user->notify(new SimpleNotification('Another'));
        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_people_cannot_open_each_others_notifications(): void
    {
        $owner = User::factory()->customer()->create();
        $owner->notify(new SimpleNotification('Private'));
        $id = $owner->notifications()->first()->id;

        $this->actingAs(User::factory()->customer()->create())->get('/notifications/'.$id)->assertNotFound();
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_confirmed_booking_page_has_qr_code_for_check_in(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(3);
        $b = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString(), 1, BookingStatus::Confirmed, ['checkin_token' => str_repeat('a', 40)]);

        $this->actingAs($b->customer)->get('/bookings/'.$b->reference)->assertOk()
            ->assertSee('data-qr="'.route('host.check-in.show', str_repeat('a', 40)).'"', false)
            ->assertSee('vendor/qrcode/qrcode-generator.js');
    }

    public function test_contact_form_stores_message_and_ignores_bots(): void
    {
        $this->get('/contact')->assertOk()->assertSee('How can we help?');
        $this->post('/contact', ['name' => 'Asha', 'email' => 'asha@example.com', 'subject' => 'Group booking', 'message' => 'Can I book for twelve people?'])
            ->assertRedirect('/contact');
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Buy', 'message' => 'Cheap things here', 'website' => 'spam.example'])
            ->assertRedirect('/contact');
        $this->assertDatabaseHas('contact_messages', ['subject' => 'Group booking']);
        $this->assertDatabaseMissing('contact_messages', ['name' => 'Bot']);
        $this->post('/contact', ['name' => '', 'email' => 'nope', 'subject' => '', 'message' => 'x'])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_signed_in_user_can_report_a_listing_once(): void
    {
        $user = User::factory()->customer()->create();
        $listing = Listing::where('slug', 'tide-toddy')->first();

        $this->post('/listings/tide-toddy/report', ['reason' => 'scam'])->assertRedirect('/login');
        $this->actingAs($user)->post('/listings/tide-toddy/report', ['reason' => 'scam', 'details' => 'Asked me to pay outside the site'])->assertRedirect();
        $this->actingAs($user)->post('/listings/tide-toddy/report', ['reason' => 'scam'])->assertRedirect();
        $this->actingAs($user)->post('/listings/tide-toddy/report', ['reason' => 'nonsense'])->assertSessionHasErrors('reason');

        $this->assertSame(1, \App\Models\Report::where('reportable_id', $listing->id)->count());
    }
}
