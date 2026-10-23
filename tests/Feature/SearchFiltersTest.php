<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\StayUnit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class SearchFiltersTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function d(int $days): string
    {
        return CarbonImmutable::now()->addDays($days)->toDateString();
    }

    public function test_fully_booked_stays_disappear_for_those_dates_only(): void
    {
        // Black Sand Glass Cabins has a single room type with 6 cabins.
        $cabin = StayUnit::where('name', 'Aurora Glass Cabin')->first();
        $this->bookStay($cabin, $this->d(20), $this->d(23), rooms: 6);

        $this->get('/stays?q=Iceland&check_in='.$this->d(21).'&check_out='.$this->d(22))->assertOk()->assertDontSee('Black Sand Glass Cabins');
        $this->get('/stays?q=Iceland&check_in='.$this->d(23).'&check_out='.$this->d(25))->assertOk()->assertSee('Black Sand Glass Cabins');
    }

    public function test_rooms_and_guests_filter(): void
    {
        // Coorg bungalow: Estate Room (2 guests, 4 rooms), Heritage Suite (3 guests, 1 room).
        // A booking uses one room type, so 4 guests fit in 2 Estate Rooms but not in 1 room of either type.
        $this->get('/stays?q=Coorg&guests=4&rooms=2')->assertSee('Misty Estate Plantation Bungalow');
        $this->get('/stays?q=Coorg&guests=4&rooms=1')->assertDontSee('Misty Estate Plantation Bungalow');
        $this->get('/stays?q=Coorg&guests=2&rooms=5')->assertDontSee('Misty Estate Plantation Bungalow');
    }

    public function test_price_range_filter_in_rupees(): void
    {
        $this->get('/stays?max_price=8000')->assertSee('Beas Riverside Cedar Cabins')->assertSee('Misty Estate Plantation Bungalow')->assertDontSee('Matterhorn View Chalet');
        $this->get('/stays?min_price=30000')->assertSee('Matterhorn View Chalet')->assertDontSee('Beas Riverside Cedar Cabins');
        $this->get('/experiences?max_price=2000')->assertSee('Sunset Mangrove Kayaking')->assertDontSee('Tulamben Wreck Dive');
        $this->get('/stays?min_price=9000&max_price=5000')->assertSee('The maximum price must be higher than the minimum.');
    }

    public function test_amenities_must_all_match(): void
    {
        $this->get('/stays?amenities[]=pool')->assertSee('Ubud Jungle Pool Villa')->assertDontSee('Matterhorn View Chalet');
        $this->get('/stays?amenities[]=pool&amenities[]=fireplace')->assertDontSee('Ubud Jungle Pool Villa');
    }

    public function test_dining_filters(): void
    {
        $this->get('/dining?cuisine=Japanese')->assertSee('Kamo Omakase Counter')->assertDontSee('Tide &amp; Toddy', false);
        $this->get('/dining?price_band=2')->assertSee('Tide &amp; Toddy', false)->assertDontSee('Kamo Omakase Counter');
        $this->get('/dining?guests=6')->assertDontSee('Kamo Omakase Counter');   // counter seats parties of up to 4
        $this->get('/dining?category=rooftop')->assertSee('Jharokha Rooftop')->assertDontSee('Osteria del Cipresso');
    }

    public function test_dining_date_and_time_filter_uses_real_slots(): void
    {
        // Estate Coffee Table only serves 08:00–13:00 → no dinner tables.
        $date = $this->d(5);
        $this->get('/dining?date='.$date.'&time=breakfast')->assertSee('Estate Coffee Table');
        $this->get('/dining?date='.$date.'&time=dinner')->assertDontSee('Estate Coffee Table')->assertSee('Jharokha Rooftop');
    }

    public function test_fully_booked_dining_date_is_excluded(): void
    {
        $listing = Listing::where('slug', 'kamo-omakase-counter')->first();
        $tz = $listing->destination->timezone;
        $date = CarbonImmutable::now($tz)->addDays(7);
        while (in_array($date->dayOfWeek, [0, 1])) {   // closed Sun & Mon
            $date = $date->addDay();
        }
        foreach ($listing->timeSlots()->whereDate('starts_at', $date->toDateString())->get() as $slot) {
            $this->bookSlot($slot, 10);
        }

        $this->get('/dining?date='.$date->toDateString())->assertDontSee('Kamo Omakase Counter');
        $this->get('/dining?date='.$date->addDay()->toDateString().'&cuisine=Japanese')->assertSee('Kamo Omakase Counter');
    }

    public function test_activity_filters(): void
    {
        $this->get('/experiences?difficulty=easy')->assertSee('Sunset Mangrove Kayaking')->assertDontSee('Tulamben Wreck Dive');
        $this->get('/experiences?duration=full')->assertSee('Matterhorn Ski Day with a Guide')->assertDontSee('Tea Ceremony in a Machiya');
        $this->get('/experiences?duration=short')->assertSee('Tea Ceremony in a Machiya')->assertDontSee('Matterhorn Ski Day with a Guide');
        $this->get('/experiences?category=skiing')->assertSee('Beginner Ski Lessons at Solang')->assertDontSee('Sunset Mangrove Kayaking');
    }

    public function test_sorting_by_price(): void
    {
        $html = $this->get('/stays?sort=price_asc')->getContent();
        $this->assertLessThan(strpos($html, 'Matterhorn View Chalet'), strpos($html, 'Beas Riverside Cedar Cabins'));
        $html = $this->get('/stays?sort=price_desc')->getContent();
        $this->assertLessThan(strpos($html, 'Beas Riverside Cedar Cabins'), strpos($html, 'Matterhorn View Chalet'));
    }

    public function test_invalid_dates_are_explained_and_ignored(): void
    {
        $this->get('/stays?check_in='.$this->d(5).'&check_out='.$this->d(3))->assertOk()
            ->assertSee('Check-out must be after check-in.')->assertSee('Matterhorn View Chalet');
        $this->get('/stays?check_in='.$this->d(1).'&check_out='.$this->d(40))->assertOk()->assertSee('at most 30 nights');
        $this->get('/dining?date=2020-01-01')->assertOk()->assertSee('Please choose today or a future date.');
    }

    public function test_active_filter_chips_and_empty_state(): void
    {
        $this->get('/stays?q=Kyoto')->assertSee('Remove filter “Kyoto”', false);
        $this->get('/stays?q=Atlantis')->assertSee('Nothing matches all of these filters.');
    }

    public function test_rating_filter_is_hidden_until_real_reviews_exist(): void
    {
        $this->get('/stays')->assertDontSee('Guest rating');
    }

    public function test_global_search_groups_results_by_category(): void
    {
        $this->get('/search?q=Kyoto')->assertOk()
            ->assertSee('Higashiyama Garden Ryokan')->assertSee('Kamo Omakase Counter')->assertSee('Tea Ceremony in a Machiya')
            ->assertDontSee('Matterhorn View Chalet');
        $this->get('/search?q=Nihon')->assertSee('Tea Ceremony in a Machiya');
    }
}
