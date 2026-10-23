<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/data/destinations.php' as $i => $d) {
            $destination = Destination::updateOrCreate(['slug' => $d['slug']], [
                'name' => $d['name'],
                'local_name' => $d['local_name'],
                'country' => $d['country'],
                'region' => $d['region'],
                'timezone' => $d['timezone'],
                'summary' => $d['summary'],
                'hero_image' => 'images/listings/destination-'.$d['slug'].'.webp',
                'latitude' => $d['lat'],
                'longitude' => $d['lng'],
                'is_featured' => $d['featured'],
                'sort_order' => $i,
            ]);

            foreach ($d['aliases'] as $alias) {
                $destination->aliases()->firstOrCreate(['alias' => $alias]);
            }
        }
    }
}
