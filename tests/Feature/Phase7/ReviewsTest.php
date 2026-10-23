<?php

namespace Tests\Feature\Phase7;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Review;
use App\Models\StayUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookings;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use CreatesBookings, RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@excursio.test')->first();
    }

    private function visit(bool $checkedIn = true, BookingStatus $status = BookingStatus::Completed, int $endedDaysAgo = 3): Booking
    {
        $unit = StayUnit::where('name', 'Alpine Double')->first();
        $out = CarbonImmutable::today()->subDays($endedDaysAgo);

        return $this->bookStay($unit, $out->subDays(2)->toDateString(), $out->toDateString(), 1, $status, [
            'checked_in_at' => $checkedIn ? $out->subDays(2)->setTime(15, 0) : null,
        ]);
    }

    private function write(Booking $b, int $rating = 5, string $body = 'Wonderful stay, spotless room and very kind hosts.')
    {
        return $this->actingAs($b->customer)->post("/bookings/{$b->reference}/review", ['rating' => $rating, 'body' => $body]);
    }

    public function test_checked_in_guest_can_review_and_it_waits_for_moderation(): void
    {
        $b = $this->visit();
        $this->actingAs($b->customer)->get("/bookings/{$b->reference}")->assertSee('How was it?');
        $this->write($b)->assertSessionHasNoErrors()->assertSessionHas('status');

        $review = Review::first();
        $this->assertSame('pending', $review->status);
        $this->assertSame(0, Listing::find($b->listing_id)->reviews_count);   // not counted until approved
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/stays/matterhorn-view-chalet')->assertDontSee('Wonderful stay, spotless room');
    }

    public function test_only_real_visits_can_be_reviewed(): void
    {
        $notCheckedIn = $this->visit(checkedIn: false);
        $this->write($notCheckedIn)->assertSessionHasErrors('review');
        $this->actingAs($notCheckedIn->customer)->get("/bookings/{$notCheckedIn->reference}")->assertSee('Reviews open once the host has checked you in');

        $cancelled = $this->visit(status: BookingStatus::Cancelled);
        $this->write($cancelled)->assertSessionHasErrors('review');

        $tooLate = $this->visit(endedDaysAgo: 75);
        $this->write($tooLate)->assertSessionHasErrors('review');

        // Someone else's booking: can't even see it.
        $other = $this->visit();
        $this->actingAs(User::factory()->customer()->create())->post("/bookings/{$other->reference}/review", ['rating' => 5, 'body' => str_repeat('Great place to stay. ', 2)])->assertNotFound();

        $this->assertSame(0, Review::count());
    }

    public function test_one_review_per_booking_and_body_rules(): void
    {
        $b = $this->visit();
        $this->write($b, 6)->assertSessionHasErrors('rating');
        $this->write($b, 5, 'Too short')->assertSessionHasErrors('body');
        $this->write($b, 5, 'Great! Book cheaper at https://example.com instead of here.')->assertSessionHasErrors('body');
        $this->write($b)->assertSessionHasNoErrors();
        $this->write($b)->assertSessionHasErrors('review');
        $this->assertSame(1, Review::count());
    }

    public function test_approval_updates_rating_and_rejection_removes_it(): void
    {
        $a = $this->visit();
        $c = $this->visit();
        $this->write($a, 5);
        $this->write($c, 2, 'The room was noisy and breakfast was cold both mornings.');
        [$ra, $rc] = [Review::where('booking_id', $a->id)->first(), Review::where('booking_id', $c->id)->first()];

        $this->actingAs($this->admin())->get('/admin/reviews')->assertOk()->assertSee('breakfast was cold');
        $this->actingAs($this->admin())->patch("/admin/reviews/{$ra->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->patch("/admin/reviews/{$rc->id}", ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin())->patch("/admin/reviews/{$rc->id}", ['decision' => 'approve']);

        $listing = Listing::find($a->listing_id);
        $this->assertSame(2, $listing->reviews_count);
        $this->assertEqualsWithDelta(3.5, $listing->rating_avg, 0.001);

        $this->actingAs($this->admin())->patch("/admin/reviews/{$rc->id}", ['decision' => 'reject', 'reason' => 'Mentions a staff member by full name']);
        $listing->refresh();
        $this->assertSame(1, $listing->reviews_count);
        $this->assertEqualsWithDelta(5.0, $listing->rating_avg, 0.001);
        $this->assertTrue($c->customer->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'full name')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.rejected', 'auditable_id' => $rc->id]);
    }

    public function test_published_review_shows_on_listing_with_privacy_and_escaping(): void
    {
        $b = $this->visit();
        $b->customer->forceFill(['name' => 'Priya Raghavan Sharma'])->save();
        $this->write($b, 4, "Lovely chalet <script>alert('x')</script> with a great view.");
        $this->actingAs($this->admin())->patch('/admin/reviews/'.Review::first()->id, ['decision' => 'approve']);
        \Illuminate\Support\Facades\Auth::logout();

        $this->get('/stays/matterhorn-view-chalet')->assertOk()
            ->assertSee('Priya S.')->assertDontSee('Raghavan')->assertDontSee($b->customer->email)
            ->assertSee('Checked-in guest')
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee("&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;", false);
    }

    public function test_host_can_reply_only_to_reviews_of_own_listings(): void
    {
        $b = $this->visit();
        $this->write($b);
        $review = Review::first();
        $this->actingAs($this->admin())->patch("/admin/reviews/{$review->id}", ['decision' => 'approve']);

        $kansai = User::where('email', 'host.kansai@excursio.test')->first();
        $this->actingAs($kansai)->put("/host/reviews/{$review->id}/reply", ['reply' => 'Not my listing'])->assertNotFound();

        $alpine = User::where('email', 'host.alpine@excursio.test')->first();
        $this->actingAs($alpine)->get('/host/reviews')->assertOk()->assertSee('spotless room');
        $this->actingAs($alpine)->put("/host/reviews/{$review->id}/reply", ['reply' => 'Thank you for staying with us!'])->assertSessionHasNoErrors();
        $this->assertSame('Thank you for staying with us!', $review->fresh()->host_reply);
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/stays/matterhorn-view-chalet')->assertSee('Thank you for staying with us!');
    }

    public function test_rating_filter_appears_once_reviews_exist(): void
    {
        $this->get('/stays')->assertDontSee('Guest rating');
        $b = $this->visit();
        $this->write($b);
        $this->actingAs($this->admin())->patch('/admin/reviews/'.Review::first()->id, ['decision' => 'approve']);
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/stays')->assertSee('Guest rating');
        $this->get('/stays?rating=4.5')->assertSee('Matterhorn View Chalet')->assertDontSee('Lake Pichola Haveli');
    }
}
