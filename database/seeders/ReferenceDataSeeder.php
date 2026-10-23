<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'stay' => [['Boutique hotel', 'bi-building'], ['Villa', 'bi-house'], ['Ryokan', 'bi-flower1'], ['Mountain lodge', 'bi-snow'],
                ['Cabin', 'bi-tree'], ['Farmhouse', 'bi-sun'], ['Homestay', 'bi-house-heart'], ['Resort', 'bi-water']],
            'dining' => [['Fine dining', 'bi-stars'], ['Tasting menu', 'bi-list-stars'], ['Rooftop', 'bi-moon-stars'],
                ['Seafood', 'bi-droplet'], ['Cafe', 'bi-cup-hot']],
            'activity' => [['Skiing', 'bi-snow2'], ['Trekking', 'bi-signpost-split'], ['Kayaking', 'bi-water'],
                ['Scuba diving', 'bi-life-preserver'], ['Camping', 'bi-fire'], ['City tour', 'bi-map'], ['Workshop', 'bi-palette']],
        ];

        foreach ($categories as $type => $items) {
            foreach ($items as $i => [$name, $icon]) {
                Category::updateOrCreate(
                    ['listing_type' => $type, 'slug' => Str::slug($name)],
                    ['name' => $name, 'icon' => $icon, 'sort_order' => $i]
                );
            }
        }

        $amenities = [
            'stay' => ['Wi-Fi' => 'bi-wifi', 'Pool' => 'bi-water', 'Breakfast' => 'bi-egg-fried', 'Fireplace' => 'bi-fire',
                'Ski storage' => 'bi-snow', 'Mountain view' => 'bi-image-alt', 'Lake view' => 'bi-droplet-half', 'Heating' => 'bi-thermometer-sun',
                'Air conditioning' => 'bi-fan', 'Garden' => 'bi-flower2', 'Private bath' => 'bi-droplet', 'Spa' => 'bi-flower3',
                'Hot tub' => 'bi-moisture', 'Parking' => 'bi-p-square', 'Kitchen' => 'bi-basket', 'Rooftop' => 'bi-building-up'],
            'dining' => ['Outdoor seating' => 'bi-tree', 'Vegetarian options' => 'bi-leaf', 'Wine list' => 'bi-cup-straw',
                'Counter seating' => 'bi-person-workspace', 'Live music' => 'bi-music-note-beamed'],
            'activity' => ['Guide included' => 'bi-person-badge', 'Equipment included' => 'bi-backpack',
                'Meal included' => 'bi-egg-fried', 'Beginner friendly' => 'bi-emoji-smile'],
        ];

        // Short slugs used by the listings data file.
        $aliases = ['wi-fi' => 'wifi', 'vegetarian-options' => 'vegetarian', 'guide-included' => 'guide'];

        foreach ($amenities as $type => $items) {
            foreach ($items as $name => $icon) {
                $slug = Str::slug($name);
                Amenity::updateOrCreate(['slug' => $aliases[$slug] ?? $slug], ['name' => $name, 'icon' => $icon, 'applies_to' => $type]);
            }
        }
    }
}
