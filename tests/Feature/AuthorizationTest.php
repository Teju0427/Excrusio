<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $customer, Listing $listing): Booking
    {
        $booking = new Booking(['guest_name' => $customer->name, 'guest_email' => $customer->email]);
        $booking->forceFill([
            'reference' => 'EXC-ST-'.Str::upper(Str::random(6)),
            'customer_id' => $customer->id, 'listing_id' => $listing->id, 'listing_type' => $listing->listing_type,
            'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addDay(), 'guests' => 2,
            'unit_price' => 100000, 'subtotal' => 100000, 'total' => 100000,
            'cancellation_policy' => $listing->cancellation_policy, 'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::DemoPaid,
        ])->save();

        return $booking;
    }

    public function test_guests_are_sent_to_login_for_private_pages(): void
    {
        foreach (['/account', '/bookings', '/host', '/admin', '/account/profile'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_roles_cannot_enter_each_others_areas(): void
    {
        $customer = User::factory()->customer()->create();
        $host = User::factory()->provider()->create();

        $this->actingAs($customer)->get('/host')->assertForbidden();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($host)->get('/admin')->assertForbidden();
        $this->actingAs($host)->get('/bookings')->assertForbidden();
        $this->actingAs($customer)->patch('/admin/users/'.$host->id.'/status', ['status' => 'suspended'])->assertForbidden();
        $this->assertSame(UserStatus::Active, $host->fresh()->status);
    }

    public function test_customer_cannot_see_another_customers_booking_by_changing_the_url(): void
    {
        $listing = Listing::first();
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $booking = $this->bookingFor($alice, $listing);

        $this->actingAs($alice)->get('/bookings/'.$booking->reference)->assertOk()->assertSee($booking->reference);
        $this->actingAs($bob)->get('/bookings/'.$booking->reference)->assertNotFound();
        $this->actingAs($bob)->get('/bookings')->assertOk()->assertDontSee($booking->reference);
    }

    public function test_only_the_listings_own_host_can_see_its_bookings(): void
    {
        $listing = Listing::first();   // owned by a seeded demo host
        $booking = $this->bookingFor(User::factory()->customer()->create(), $listing);

        $this->actingAs($listing->provider)->get('/bookings/'.$booking->reference)->assertOk();
        $this->actingAs(User::factory()->provider()->create())->get('/bookings/'.$booking->reference)->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/bookings/'.$booking->reference)->assertOk();
    }

    public function test_listing_policy_ownership(): void
    {
        $listing = Listing::first();
        $owner = $listing->provider;
        $otherHost = User::factory()->provider()->create();

        $this->assertTrue($owner->can('update', $listing));
        $this->assertFalse($otherHost->can('update', $listing));
        $this->assertTrue(User::factory()->admin()->create()->can('update', $listing));
        $this->assertFalse(User::factory()->customer()->create()->can('update', $listing));
        $this->assertFalse(User::factory()->pendingProvider()->create()->can('create', Listing::class));
    }

    public function test_draft_listing_is_visible_only_to_its_host_and_admins(): void
    {
        $listing = Listing::where('slug', 'matterhorn-view-chalet')->first();
        $listing->forceFill(['status' => ListingStatus::Draft])->save();
        $url = '/stays/matterhorn-view-chalet';

        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertNotFound();
        $this->actingAs($listing->provider)->get($url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }

    public function test_admin_approves_a_pending_host_and_it_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->pendingProvider()->create();

        $this->actingAs($admin)->get('/admin/users?status=pending')->assertOk()->assertSee($host->email)->assertSee('Approve');
        $this->patch('/admin/users/'.$host->id.'/status', ['status' => 'active'])->assertSessionHas('status');

        $this->assertSame(UserStatus::Active, $host->fresh()->status);
        $log = AuditLog::where('action', 'user.approved')->first();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($host->id, $log->auditable_id);

        $this->actingAs($host->fresh())->get('/host')->assertOk();
    }

    public function test_admin_cannot_suspend_admins_or_send_invalid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->patch('/admin/users/'.$other->id.'/status', ['status' => 'suspended'])->assertStatus(422);
        $this->patch('/admin/users/'.$customer->id.'/status', ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->assertSame(UserStatus::Active, $customer->fresh()->status);

        $demoGuest = User::where('email', 'guest@excursio.test')->first();
        $this->patch('/admin/users/'.$demoGuest->id.'/status', ['status' => 'suspended'])->assertStatus(422);
        $this->assertSame(UserStatus::Active, $demoGuest->fresh()->status);
    }

    public function test_dashboards_render_for_each_role(): void
    {
        $this->post('/demo-login/customer');
        $this->get('/account')->assertOk()->assertSee('Hello');
        $this->post('/logout');

        $this->post('/demo-login/provider');
        $this->get('/host')->assertOk()->assertSee('Matterhorn View Chalet');
        $this->post('/logout');

        $this->post('/demo-login/admin');
        $this->get('/admin')->assertOk()->assertSee('Platform overview');
        $this->get('/admin/users')->assertOk();
    }
}
