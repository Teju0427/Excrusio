<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_three_category_rows_from_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find your next extraordinary experience.')
            ->assertSee('Featured stays')
            ->assertSee('Exceptional dining')
            ->assertSee('Adventures &amp; experiences', false)
            ->assertSee('Matterhorn View Chalet');
    }

    public function test_browse_pages_only_show_their_own_category(): void
    {
        $this->get('/stays')->assertOk()->assertSee('Higashiyama Garden Ryokan')->assertDontSee('Kamo Omakase Counter');
        $this->get('/dining')->assertOk()->assertSee('Kamo Omakase Counter')->assertDontSee('Higashiyama Garden Ryokan');
        $this->get('/experiences')->assertOk()->assertSee('Tea Ceremony in a Machiya');
    }

    public function test_search_matches_destination_aliases(): void
    {
        // Feature F6: "Nihon" → Japan (Kyoto), "Kodagu" → Coorg
        $this->get('/stays?q=Nihon')->assertOk()->assertSee('Higashiyama Garden Ryokan')->assertDontSee('Matterhorn View Chalet');
        $this->get('/stays?q=日本')->assertOk()->assertSee('Higashiyama Garden Ryokan');
        $this->get('/stays?q=Kodagu')->assertOk()->assertSee('Misty Estate Plantation Bungalow');
    }

    public function test_guest_filter_hides_stays_that_are_too_small(): void
    {
        // The Iceland listing has 6 cabins × 2 guests = 12 places at most.
        $this->get('/stays?q=Iceland&guests=13')->assertOk()->assertDontSee('Black Sand Glass Cabins');
        $this->get('/stays?q=Iceland&guests=2')->assertOk()->assertSee('Black Sand Glass Cabins');
    }

    public function test_detail_page_loads_and_wrong_category_is_404(): void
    {
        $this->get('/stays/matterhorn-view-chalet')->assertOk()->assertSee('Alpine Double')->assertSee('₹38,500');
        $this->get('/dining/matterhorn-view-chalet')->assertNotFound();
        $this->get('/stays/does-not-exist')->assertNotFound();
    }

    public function test_unpublished_listing_is_hidden(): void
    {
        $listing = Listing::where('slug', 'matterhorn-view-chalet')->first();
        $listing->forceFill(['status' => ListingStatus::Draft])->save();

        $this->get('/stays/matterhorn-view-chalet')->assertNotFound();
        $this->get('/stays')->assertDontSee('Matterhorn View Chalet');
    }

    public function test_invalid_filter_input_is_ignored_and_explained(): void
    {
        $this->get('/stays?sort=DROP%20TABLE')->assertOk()->assertSee('These filters were ignored');
        $this->get('/stays?guests=999')->assertOk()->assertSee('Groups of more than 20');
    }

    public function test_destination_and_info_pages(): void
    {
        $this->get('/destinations')->assertOk()->assertSee('Kyoto')->assertSee('京都');
        $this->get('/destinations/kyoto')->assertOk()->assertSee('Stays in Kyoto');
        foreach (['/about', '/terms', '/privacy', '/image-credits'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_listings_without_reviews_never_show_a_fake_rating(): void
    {
        $this->get('/stays')->assertSee('No reviews yet');
    }

    public function test_home_page_works_when_served_from_the_database_cache(): void
    {
        // Regression: production uses the database cache store; the second request reads from it.
        config(['cache.default' => 'database']);

        $this->get('/')->assertOk()->assertSee('Matterhorn View Chalet');
        $this->get('/')->assertOk()->assertSee('Matterhorn View Chalet');
    }
}
