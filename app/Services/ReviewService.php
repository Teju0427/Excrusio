<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Notifications\SimpleNotification;
use Illuminate\Support\Facades\DB;

/**
 * Reviews come only from guests who actually showed up: the host checked them in (QR or
 * reference). Every review is moderated before it appears, and listing ratings are
 * recalculated from approved reviews only.
 */
class ReviewService
{
    public const WINDOW_DAYS = 60;

    public function __construct(private AuditLogger $audit) {}

    /** Why this guest can't review this booking, or null if they can. */
    public function ineligibleReason(Booking $booking, User $user): ?string
    {
        return match (true) {
            $booking->customer_id === null || $booking->customer_id !== $user->id => 'Only the guest who booked can review it.',
            $booking->review()->exists() => 'You’ve already reviewed this booking.',
            ! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Completed], true) => 'Only confirmed or completed bookings can be reviewed.',
            $booking->checked_in_at === null => 'Reviews open once the host has checked you in — so every review is from a real visit.',
            $booking->ends_at->copy()->addDays(self::WINDOW_DAYS)->isPast() => 'Reviews can be written up to '.self::WINDOW_DAYS.' days after your visit.',
            default => null,
        };
    }

    public function submit(Booking $booking, User $user, int $rating, string $body): Review
    {
        if ($reason = $this->ineligibleReason($booking, $user)) {
            throw new \App\Exceptions\BookingException($reason);
        }

        $review = new Review(['rating' => $rating, 'body' => trim($body)]);
        $review->forceFill(['booking_id' => $booking->id, 'listing_id' => $booking->listing_id, 'customer_id' => $user->id, 'status' => 'pending'])->save();
        $this->audit->log('review.submitted', $review, [], ['rating' => $rating]);

        return $review;
    }

    public function approve(Review $review, User $admin): void
    {
        DB::transaction(function () use ($review, $admin) {
            $review->forceFill(['status' => 'approved', 'rejection_reason' => null, 'moderated_by' => $admin->id, 'moderated_at' => now()])->save();
            $this->recalculate($review->listing_id);
        });
        $this->audit->log('review.approved', $review, [], []);
        $review->loadMissing('listing.provider', 'customer');
        $review->customer?->notify(new SimpleNotification('Your review of '.$review->listing->title.' is now live. Thank you!', $review->listing->url()));
        $review->listing->provider->notify(new SimpleNotification("New {$review->rating}-star review for {$review->listing->title}.", route('host.reviews', absolute: false)));
    }

    public function reject(Review $review, User $admin, string $reason): void
    {
        DB::transaction(function () use ($review, $admin, $reason) {
            $review->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'moderated_by' => $admin->id, 'moderated_at' => now()])->save();
            $this->recalculate($review->listing_id);   // in case it had been approved before
        });
        $this->audit->log('review.rejected', $review, [], ['reason' => $reason]);
        $review->loadMissing('listing', 'customer', 'booking');
        $review->customer?->notify(new SimpleNotification('Your review of '.$review->listing->title.' wasn’t published: '.$reason, route('bookings.show', $review->booking, false)));
    }

    public function reply(Review $review, User $host, ?string $reply): void
    {
        $review->forceFill(['host_reply' => $reply ?: null, 'host_replied_at' => $reply ? now() : null])->save();
        $this->audit->log('review.replied', $review, [], ['by' => $host->id]);
    }

    /** Listing rating = average of approved reviews only. */
    public function recalculate(int $listingId): void
    {
        $stats = Review::approved()->where('listing_id', $listingId)->selectRaw('count(*) n, avg(rating) a')->first();
        Listing::whereKey($listingId)->update([
            'reviews_count' => (int) $stats->n,
            'rating_avg' => $stats->n ? round((float) $stats->a, 2) : null,
        ]);
        \Illuminate\Support\Facades\Cache::forget('home:rows');   // homepage order uses review counts
    }
}
