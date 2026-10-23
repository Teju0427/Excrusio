<?php

namespace Tests\Feature\Dashboards;

use App\Enums\BookingStatus;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Report;
use App\Models\StayUnit;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@excursio.test')->first();
    }

    public function test_every_admin_page_is_admin_only(): void
    {
        $pages = ['/admin', '/admin/listings', '/admin/bookings', '/admin/categories', '/admin/reports', '/admin/messages', '/admin/audit', '/admin/users'];
        $host = User::where('email', 'host.alpine@excursio.test')->first();
        foreach ($pages as $page) {
            \Illuminate\Support\Facades\Auth::logout();
            $this->get($page)->assertRedirect('/login');
            $this->actingAs($host)->get($page)->assertForbidden();
            $this->actingAs($this->admin())->get($page)->assertOk();
        }
    }

    public function test_analytics_come_from_real_records(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $slot = TimeSlot::whereHas('listing', fn ($q) => $q->where('slug', 'old-city-heritage-walk'))->where('starts_at', '>', now()->addDays(3))->first();
        $in = CarbonImmutable::today()->addDays(7);
        $stay = $this->bookStay($unit, $in->toDateString(), $in->addDays(2)->toDateString());
        $this->bookSlot($slot, 3);
        $this->bookSlot($slot, 1, BookingStatus::Cancelled);
        Payment::create(['booking_id' => $stay->id, 'gateway' => 'demo', 'type' => 'charge', 'amount' => 500000, 'status' => 'succeeded']);
        Payment::create(['booking_id' => $stay->id, 'gateway' => 'demo', 'type' => 'refund', 'amount' => 125000, 'status' => 'succeeded']);
        Payment::create(['booking_id' => $stay->id, 'gateway' => 'demo', 'type' => 'charge', 'amount' => 999999, 'status' => 'failed']);

        $stats = app(AnalyticsService::class)->admin();
        $this->assertSame(['stay' => 1, 'dining' => 0, 'activity' => 1], $stats['byType']);   // cancelled excluded
        $this->assertSame(1, (int) $stats['byStatus']['cancelled']);
        $this->assertSame(375000, $stats['revenue30']);                                       // charges − refunds; failed ignored
        $this->assertSame(['Swiss Alps' => 1, 'Udaipur' => 1], $stats['destinations']->map(fn ($n) => (int) $n)->sortKeys()->all());
        $this->assertSame(3, (int) $stats['topActivities']->first()->people);
        $this->assertSame(1, array_sum(array_column([$stats['trend']['stay']], count($stats['weeks']) - 1)));

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('data-chart-for="admin-trend"', false)->assertSee('data-chart-for="admin-types"', false)
            ->assertSee('Most-booked experiences')->assertSee('Old City Heritage Walk')->assertSee('₹3,750');
    }

    public function test_admin_cancellation_refunds_in_full_and_notifies_guest(): void
    {
        $unit = StayUnit::where('name', 'Courtyard Room')->first();   // instant-book villa in Goa
        $guest = User::factory()->customer()->create();
        $svc = app(BookingService::class);
        $in = CarbonImmutable::now()->addDays(2);   // inside the no-refund window for a guest
        $b = $svc->checkout($svc->holdStay($guest, $unit, $in->toDateString(), $in->addDays(3)->toDateString(), 1, 2), ['guest_name' => $guest->name, 'guest_email' => $guest->email]);

        $this->actingAs($this->admin())->post("/admin/bookings/{$b->reference}/cancel", ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin())->post("/admin/bookings/{$b->reference}/cancel", ['reason' => 'Property flooded'])->assertSessionHasNoErrors();

        $b->refresh();
        $this->assertSame(BookingStatus::Cancelled, $b->status);
        $this->assertSame($b->total, $b->refund_amount);
        $this->assertTrue($guest->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'Excursio support')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.cancelled', 'auditable_id' => $b->id, 'user_id' => $this->admin()->id]);
        // Cancelling again is refused, not double-refunded.
        $this->actingAs($this->admin())->post("/admin/bookings/{$b->reference}/cancel", ['reason' => 'Property flooded'])->assertSessionHasErrors('booking');
        $this->assertSame(1, Payment::where('booking_id', $b->id)->where('type', 'refund')->count());
    }

    public function test_booking_search_and_filters(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(9);
        $a = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString(), 1, BookingStatus::Confirmed, ['guest_name' => 'Meera Pillai']);
        $b = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString(), 1, BookingStatus::Cancelled, ['guest_name' => 'Arjun Rao']);

        $this->actingAs($this->admin())->get('/admin/bookings?q=Meera')->assertSee($a->reference)->assertDontSee($b->reference);
        $this->actingAs($this->admin())->get('/admin/bookings?status=cancelled')->assertSee($b->reference)->assertDontSee($a->reference);
        $this->actingAs($this->admin())->get('/admin/bookings?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_unpublishing_hides_listing_but_keeps_bookings(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(9);
        $b = $this->bookStay($unit, $in->toDateString(), $in->addDay()->toDateString());

        $this->actingAs($this->admin())->patch('/admin/listings/matterhorn-view-chalet', ['decision' => 'unpublish'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/stays/matterhorn-view-chalet')->assertNotFound();
        $this->get('/stays')->assertDontSee('Matterhorn View Chalet');
        $this->assertSame(BookingStatus::Confirmed, $b->fresh()->status);
        $this->actingAs($b->customer)->get('/bookings/'.$b->reference)->assertOk();
    }

    public function test_category_management(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/categories', ['listing_type' => 'activity', 'name' => 'Stargazing', 'icon' => 'bi-stars'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/categories', ['listing_type' => 'activity', 'name' => 'Stargazing'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/admin/categories', ['listing_type' => 'activity', 'name' => 'Bad', 'icon' => '"><script>'])->assertSessionHasErrors('icon');
        $new = Category::where('slug', 'stargazing')->first();
        $this->assertNotNull($new);

        $used = Listing::first()->category;
        $this->actingAs($admin)->delete("/admin/categories/{$used->id}")->assertSessionHasErrors('booking');
        $this->assertNotNull($used->fresh());
        $this->actingAs($admin)->put("/admin/categories/{$used->id}", ['name' => $used->name, 'icon' => $used->icon])->assertSessionHasNoErrors();
        $this->assertFalse($used->fresh()->is_active);   // unchecked switch hides it

        $this->actingAs($admin)->delete("/admin/categories/{$new->id}")->assertSessionHasNoErrors();
        $this->assertNull($new->fresh());
    }

    public function test_reports_and_messages_can_be_handled(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->post('/listings/tide-toddy/report', ['reason' => 'safety', 'details' => 'Broken stairs']);
        $report = Report::first();
        ContactMessage::create(['name' => 'Asha', 'email' => 'asha@example.com', 'subject' => 'Hello', 'message' => 'A question about hosting']);

        $this->actingAs($this->admin())->get('/admin')->assertSee('1 open report')->assertSee('1 unread message');
        $this->actingAs($this->admin())->get('/admin/reports')->assertSee('Safety concern')->assertSee('Broken stairs')->assertSee('Tide');
        $this->actingAs($this->admin())->patch("/admin/reports/{$report->id}", ['status' => 'resolved'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame($this->admin()->id, $report->fresh()->resolved_by);

        $msg = ContactMessage::first();
        $this->actingAs($this->admin())->get('/admin/messages')->assertSee('A question about hosting');
        $this->actingAs($this->admin())->patch("/admin/messages/{$msg->id}");
        $this->assertNotNull($msg->fresh()->read_at);
    }

    public function test_audit_log_lists_and_filters_actions(): void
    {
        $this->actingAs($this->admin())->patch('/admin/listings/tide-toddy', ['decision' => 'unpublish']);
        $this->actingAs($this->admin())->post('/admin/categories', ['listing_type' => 'stay', 'name' => 'Treehouses']);

        $this->actingAs($this->admin())->get('/admin/audit')->assertSee('listing.unpublished')->assertSee('category.created');
        $this->actingAs($this->admin())->get('/admin/audit?action=listing.')->assertSee('listing.unpublished')->assertDontSee('category.created');
        $this->actingAs($this->admin())->get('/admin/audit?action=%27%20or%201=1')->assertSessionHasErrors('action');
    }
}
