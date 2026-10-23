<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;

/**
 * Sample reviews for the synthetic demo listings (clearly labelled as sample data on the site).
 * Only bookings that were checked in get one — the same rule real guests follow.
 */
class DemoReviewSeeder extends Seeder
{
    private const TEXT = [
        'stay' => [
            5 => [
                'Woke up to the most incredible view every morning. The room was spotless and the staff remembered our names from day one.',
                'Exactly as pictured, only better. Quiet, beautifully kept, and the breakfast alone was worth the trip.',
                'We came for two nights and wished we had booked five. Thoughtful touches everywhere and a very comfortable bed.',
                'Check-in took two minutes with the QR code and the host gave us a hand-drawn map of the area. Lovely stay.',
                'Calm, clean and full of character. Perfect base for exploring, and the hot water never ran out.',
            ],
            4 => [
                'Lovely place with warm hosts. The room was a little smaller than expected but very comfortable.',
                'Great location and a peaceful atmosphere. Wi-Fi was patchy in the evenings, otherwise excellent.',
                'Beautiful setting and good food. Would stay again, though parking was a bit of a squeeze.',
            ],
            3 => ['Nice enough for a short stay. The view is the highlight; the bathroom could use some updating.'],
            2 => ['The setting is lovely but our room was noisy at night and housekeeping missed a day.'],
        ],
        'dining' => [
            5 => [
                'One of the best meals of our trip. Every course had a story and the service was warm without being fussy.',
                'Booked a table for my parents’ anniversary and they made it feel really special. The dessert was unforgettable.',
                'Seasonal, generous and beautifully cooked. Ask for a table by the window if you can.',
                'Arrived a little early and they seated us straight away. Flavours were bright and the portions were perfect.',
            ],
            4 => [
                'Excellent food and a relaxed atmosphere. It got loud later in the evening, but we would come back.',
                'Very good tasting menu; one course felt a bit rushed. Staff were lovely throughout.',
            ],
            3 => ['Good food, though we waited a while between courses on a busy Saturday.'],
        ],
        'activity' => [
            5 => [
                'Our guide was patient, funny and clearly loves what they do. Felt completely safe the whole time.',
                'The highlight of the holiday. Small group, great equipment and plenty of time to take photos.',
                'Perfect for beginners — I had never tried this before and left wanting to do it again tomorrow.',
                'Well organised from start to finish. They checked the weather the day before and kept us updated.',
            ],
            4 => [
                'Really enjoyable and well paced. Bring an extra layer — it got colder than we expected.',
                'Great experience overall; the meeting point was a little hard to find the first time.',
            ],
            3 => ['Fun, but the group was larger than we expected so we got less time with the guide.'],
            1 => ['Started very late and felt rushed. The scenery was beautiful, but the organisation let it down.'],
        ],
    ];

    private const REPLIES = [
        'Thank you so much for staying with us — we hope to welcome you back soon!',
        'We’re delighted you enjoyed it. Thank you for the kind words.',
        'Thank you for the honest feedback. We’ve passed it on to the team and are already working on it.',
    ];

    public function run(ReviewService $service): void
    {
        mt_srand(7);
        $admin = User::where('role', 'admin')->first();
        $demoGuest = User::where('email', 'guest@excursio.test')->value('id');

        $bookings = Booking::where('status', 'completed')->whereNotNull('checked_in_at')->whereNotNull('customer_id')
            ->where('customer_id', '!=', $demoGuest)->orderBy('id')->get(['id', 'listing_id', 'listing_type', 'customer_id', 'ends_at']);
        $perListing = [];
        $used = [];
        $reviewers = [];

        foreach ($bookings as $b) {
            $who = $b->listing_id.'-'.$b->customer_id;
            if (mt_rand(1, 100) > 22 || ($perListing[$b->listing_id] ?? 0) >= 9 || isset($reviewers[$who])) {
                continue;
            }
            $rating = $this->rating();
            $pool = self::TEXT[$b->listing_type->value];
            if (! isset($pool[$rating])) {   // nearest rating we have sample text for
                $keys = array_keys($pool);
                usort($keys, fn ($a, $c) => abs($a - $rating) <=> abs($c - $rating));
                $rating = $keys[0];
            }
            // Never repeat the same sample text on one listing.
            $texts = array_values(array_diff($pool[$rating], $used[$b->listing_id] ?? []));
            if (! $texts) {
                continue;
            }
            $written = $b->ends_at->copy()->addDays(mt_rand(1, 6));
            $pending = false;

            $body = $texts[array_rand($texts)];
            $used[$b->listing_id][] = $body;
            $review = new Review(['rating' => $rating, 'body' => $body]);
            $review->forceFill([
                'booking_id' => $b->id, 'listing_id' => $b->listing_id, 'customer_id' => $b->customer_id,
                'status' => $pending ? 'pending' : 'approved',
                'moderated_by' => $pending ? null : $admin?->id, 'moderated_at' => $pending ? null : $written->addHours(5),
                'host_reply' => ! $pending && mt_rand(1, 100) <= 35 ? self::REPLIES[$rating >= 4 ? mt_rand(0, 1) : 2] : null,
                'created_at' => $written, 'updated_at' => $written,
            ]);
            $review->host_replied_at = $review->host_reply ? $written->addDay() : null;
            $review->save();
            $perListing[$b->listing_id] = ($perListing[$b->listing_id] ?? 0) + 1;
            $reviewers[$who] = true;   // one review per guest per listing
        }

        // The newest few wait in the admin moderation queue, so the demo admin has something to review.
        Review::latest('created_at')->limit(4)->get()->each(fn ($r) => $r->forceFill(['status' => 'pending', 'moderated_by' => null, 'moderated_at' => null, 'host_reply' => null, 'host_replied_at' => null])->save());

        Listing::pluck('id')->each(fn ($id) => $service->recalculate($id));
    }

    private function rating(): int
    {
        $roll = mt_rand(1, 100);

        return match (true) { $roll <= 55 => 5, $roll <= 85 => 4, $roll <= 95 => 3, $roll <= 99 => 2, default => 1 };
    }
}
