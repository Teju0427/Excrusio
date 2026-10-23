<?php

namespace Tests\Feature\Phase7;

use App\Models\Booking;
use App\Models\StayUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString('camera=(self)', $r->headers->get('Permissions-Policy'));
    }

    public function test_pages_contain_no_inline_javascript(): void
    {
        $host = User::where('email', 'host.alpine@excursio.test')->first();
        $admin = User::where('email', 'admin@excursio.test')->first();
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $b = $this->bookStay($unit, CarbonImmutable::today()->addDays(5)->toDateString(), CarbonImmutable::today()->addDays(6)->toDateString());

        $pages = [
            [null, '/'], [null, '/stays'], [null, '/stays/matterhorn-view-chalet'], [null, '/login'], [null, '/contact'],
            [$b->customer, '/bookings/'.$b->reference], [$b->customer, '/account'],
            [$host, '/host'], [$host, '/host/calendar'], [$host, '/host/listings/osteria-del-cipresso/availability'], [$host, '/host/bookings'],
            [$admin, '/admin'], [$admin, '/admin/audit'],
        ];
        foreach ($pages as [$user, $url]) {
            \Illuminate\Support\Facades\Auth::logout();
            $html = ($user ? $this->actingAs($user) : $this)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|load|error|input)\s*=/i', $html, "Inline handler on {$url}");
            // Only external scripts or JSON data blocks.
            preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $html, $m);
            $this->assertEmpty($m[0], "Inline <script> on {$url}");
        }
    }

    public function test_booking_references_cannot_be_enumerated(): void
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $b = $this->bookStay($unit, CarbonImmutable::today()->addDays(5)->toDateString(), CarbonImmutable::today()->addDays(6)->toDateString());
        $stranger = User::factory()->customer()->create();
        $otherHost = User::where('email', 'host.kansai@excursio.test')->first();

        // Existing and non-existing references look the same to outsiders.
        $this->actingAs($stranger)->get('/bookings/'.$b->reference)->assertNotFound();
        $this->actingAs($stranger)->get('/bookings/EXC-ST-NOPE00')->assertNotFound();
        $this->actingAs($otherHost)->get('/bookings/'.$b->reference)->assertNotFound();
        $this->actingAs($stranger)->post("/bookings/{$b->reference}/cancel")->assertNotFound();
        $this->actingAs($otherHost)->post("/host/bookings/{$b->reference}/confirm")->assertNotFound();
        $this->assertSame('confirmed', $b->fresh()->status->value);
    }

    public function test_roles_cannot_be_escalated_at_sign_up(): void
    {
        $this->post('/register', [
            'name' => 'Mallory', 'email' => 'mallory@example.com', 'password' => 'secret123A', 'password_confirmation' => 'secret123A',
            'role' => 'admin', 'status' => 'active', 'is_demo' => 1,
        ]);
        $u = User::where('email', 'mallory@example.com')->first();
        if ($u) {
            $this->assertNotSame('admin', $u->role->value);
            $this->assertFalse($u->is_demo);
        } else {
            $this->assertTrue(session()->has('errors'));
        }
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'guest@excursio.test', 'password' => 'wrong-'.$i]);
        }
        $this->post('/login', ['email' => 'guest@excursio.test', 'password' => config('excursio.demo_password')])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_demo_reset_refuses_in_production_without_opt_in(): void
    {
        $this->app['env'] = 'production';
        config(['excursio.demo_reset_allowed' => false]);
        $this->artisan('excursio:demo-reset', ['--force' => true])->assertExitCode(1);
        $this->assertGreaterThan(0, User::count());
    }

    public function test_server_never_trusts_browser_prices(): void
    {
        $guest = User::factory()->customer()->create();
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $in = CarbonImmutable::today()->addDays(12);
        $this->actingAs($guest)->post('/book/stay/matterhorn-view-chalet', [
            'stay_unit_id' => $unit->id, 'check_in' => $in->toDateString(), 'check_out' => $in->addDays(2)->toDateString(),
            'rooms' => 1, 'guests' => 2, 'total' => 1, 'unit_price' => 1, 'price' => 1,
        ]);
        $booking = Booking::where('customer_id', $guest->id)->first();
        $this->assertNotNull($booking);
        $this->assertGreaterThan(100, $booking->total);
        $this->assertSame(app(\App\Services\PricingService::class)->quoteStay($unit, $in, $in->addDays(2), 1)['total'], $booking->total);
    }
}
