<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WishlistAndWeatherTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_and_unsave_a_listing(): void
    {
        $user = User::factory()->customer()->create();
        $listing = Listing::where('slug', 'lake-pichola-haveli')->first();

        $this->actingAs($user)->postJson('/wishlist/'.$listing->slug)->assertOk()->assertJson(['saved' => true]);
        $this->get('/account/wishlist')->assertOk()->assertSee('Lake Pichola Haveli');
        $this->get('/stays/lake-pichola-haveli')->assertSee('aria-pressed="true"', false);

        $this->postJson('/wishlist/'.$listing->slug)->assertJson(['saved' => false]);
        $this->assertSame(0, $user->wishlist()->count());
    }

    public function test_wishlist_works_without_javascript(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->from('/stays')->post('/wishlist/lake-pichola-haveli')->assertRedirect('/stays')->assertSessionHas('status');
        $this->assertSame(1, $user->wishlist()->count());
    }

    public function test_guests_and_hosts_cannot_use_the_wishlist(): void
    {
        $this->post('/wishlist/lake-pichola-haveli')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->provider()->create())->post('/wishlist/lake-pichola-haveli')->assertForbidden();
    }

    public function test_forecast_is_shown_for_weather_dependent_activities(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['daily' => [
            'time' => [now()->toDateString(), now()->addDay()->toDateString()],
            'weather_code' => [71, 0], 'temperature_2m_max' => [-2.4, 3.1], 'temperature_2m_min' => [-9.8, -4],
            'precipitation_probability_max' => [80, 5],
        ]])]);

        $this->get('/experiences/matterhorn-ski-day-with-a-guide')->assertOk()
            ->assertSee('Weather this week')->assertSee('bi-cloud-snow', false)->assertSee('80%');
        // Indoor activity: no forecast and no API call needed.
        $this->get('/experiences/tea-ceremony-in-a-machiya')->assertDontSee('Weather this week');
    }

    public function test_weather_outage_never_breaks_the_page(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response('down', 503)]);

        $this->get('/experiences/matterhorn-ski-day-with-a-guide')->assertOk()->assertDontSee('Weather this week');
        $this->assertNull(app(WeatherService::class)->forecast(46.0, 7.7, 'Europe/Zurich'));
    }

    public function test_weather_codes_are_described(): void
    {
        $this->assertSame(['Clear', 'bi-sun'], WeatherService::describe(0));
        $this->assertSame(['Snow', 'bi-cloud-snow'], WeatherService::describe(73));
        $this->assertSame(['Thunderstorm', 'bi-cloud-lightning-rain'], WeatherService::describe(95));
    }

    public function test_listing_page_has_map_and_availability_widget(): void
    {
        $this->get('/stays/matterhorn-view-chalet')->assertSee('id="ex-map"', false)->assertSee('Check availability');
        $this->get('/dining/jharokha-rooftop')->assertSee('See available times');
    }
}
