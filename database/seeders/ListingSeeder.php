<?php

namespace Database\Seeders;

use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Listing;
use App\Models\User;
use App\Services\SlotGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ListingSeeder extends Seeder
{
    public function run(SlotGenerator $slots): void
    {
        $providers = User::where('role', UserRole::Provider->value)->orderBy('id')->get();
        $destinations = Destination::pluck('id', 'slug');
        $amenities = Amenity::pluck('id', 'slug');
        $photos = collect(json_decode(file_get_contents(public_path('images/listings/credits.json')), true))->groupBy('for');

        foreach (require __DIR__.'/data/listings.php' as $i => $row) {
            $destination = Destination::find($destinations[$row['dest']]);
            $category = Category::where('listing_type', $row['type'])->where('slug', $row['cat'])->firstOrFail();
            $slug = Str::slug($row['title']);

            $listing = Listing::firstOrNew(['slug' => $slug]);
            $listing->fill([
                'destination_id' => $destination->id,
                'category_id' => $category->id,
                'listing_type' => $row['type'],
                'title' => $row['title'],
                'slug' => $slug,
                'summary' => $row['summary'],
                'description' => $row['description'],
                'address' => $destination->region.', '.$destination->country,
                // Spread listings slightly around the destination centre for the map.
                'latitude' => $destination->latitude + (crc32($slug) % 200 - 100) / 10000,
                'longitude' => $destination->longitude + (crc32(strrev($slug)) % 200 - 100) / 10000,
                'base_price' => $row['price'] * 100,
                'booking_mode' => $row['mode'],
                'cancellation_policy' => $row['policy'],
            ]);
            $listing->forceFill([
                'provider_id' => $providers[$row['provider']]->id,
                'status' => ListingStatus::Published,
                'is_featured' => $row['featured'],
                'is_illustrative' => true,
                'published_at' => now()->subHours($i), // fixed, predictable order for "Newest"
            ])->save();

            match ($row['type']) {
                'stay' => $listing->stayDetail()->updateOrCreate([], $row['detail']),
                'dining' => $listing->diningDetail()->updateOrCreate([], array_merge($row['detail'], [
                    'deposit_per_guest' => ($row['detail']['deposit_per_guest'] ?? 0) * 100,
                ])),
                'activity' => $listing->activityDetail()->updateOrCreate([], $row['detail']),
            };

            foreach ($row['units'] ?? [] as [$name, $guests, $qty, $price, $beds]) {
                $listing->stayUnits()->updateOrCreate(['name' => $name], [
                    'max_guests' => $guests, 'quantity' => $qty,
                    'price_per_night' => $price * 100, 'bed_configuration' => $beds,
                ]);
            }

            // Weekend price rule for stays (Fri + Sat nights: bits 5 and 6).
            if ($row['type'] === 'stay') {
                foreach ($listing->stayUnits as $unit) {
                    $unit->priceRules()->updateOrCreate(['label' => 'Weekend'], [
                        'listing_id' => $listing->id,
                        'start_date' => now()->startOfYear(),
                        'end_date' => now()->addYear()->endOfYear(),
                        'weekday_mask' => (1 << 5) | (1 << 6),
                        'price' => (int) round($unit->price_per_night * 1.15),
                    ]);
                }
            }

            if (! empty($row['schedules'])) {
                $listing->schedules()->delete();
                foreach ($row['schedules'] as $s) {
                    $listing->schedules()->create([
                        'weekday' => $s['weekday'], 'start_time' => $s['start'], 'end_time' => $s['end'],
                        'interval_minutes' => $s['every'], 'slot_length_minutes' => $s['length'], 'capacity' => $s['capacity'],
                    ]);
                }
                $slots->generate($listing->fresh());
            }

            $listing->amenities()->sync(collect($row['amenities'])->map(fn ($a) => $amenities[$a] ?? null)->filter()->all());

            // Hand-picked, freely-licensed photos (see public/images/listings/credits.json).
            $listing->images()->delete();
            foreach ($photos->get($slug, []) as $i => $photo) {
                $listing->images()->create([
                    'path' => 'images/listings/'.$photo['file'],
                    'alt' => $photo['description'],
                    'credit' => $photo['author'],
                    'license' => $photo['license'],
                    'sort_order' => $i,
                    'is_cover' => $i === 0,
                ]);
            }
        }
    }
}
