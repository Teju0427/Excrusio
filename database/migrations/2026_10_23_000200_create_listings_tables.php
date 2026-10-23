<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('listing_type', 20);                 // stay | dining | activity
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->string('summary', 300);
            $table->text('description');
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Money is stored in paise (₹1 = 100) to avoid rounding errors.
            $table->unsignedInteger('base_price');
            $table->string('booking_mode', 20)->default('instant');        // instant | request
            $table->string('cancellation_policy', 20)->default('moderate'); // flexible | moderate | strict
            $table->string('status', 20)->default('draft');      // draft | pending_review | published | rejected | archived
            $table->string('rejection_reason', 255)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_illustrative')->default(true);  // seeded demo data flag
            $table->decimal('rating_avg', 3, 2)->nullable();    // cached from approved reviews only
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['listing_type', 'status', 'destination_id']);
            $table->index(['status', 'is_featured']);
            $table->index('base_price');
        });

        Schema::create('stay_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('check_in_time')->default('14:00');
            $table->time('check_out_time')->default('11:00');
            $table->text('house_rules')->nullable();
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('max_nights')->default(30);
        });

        Schema::create('dining_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cuisine', 80);
            $table->unsignedTinyInteger('price_band');           // 1–4 → ₹ to ₹₹₹₹
            $table->string('dress_code', 80)->nullable();
            $table->unsignedSmallInteger('max_party_size')->default(8);
            $table->unsignedInteger('deposit_per_guest')->default(0); // paise; 0 = pay at restaurant
            $table->text('policies')->nullable();
        });

        Schema::create('activity_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('difficulty', 20)->nullable();        // easy | moderate | challenging
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('equipment')->nullable();
            $table->text('safety_info')->nullable();
            $table->boolean('weather_dependent')->default(false);
            $table->unsignedSmallInteger('booking_cutoff_hours')->default(12);
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');                              // relative to /public or full URL
            $table->string('alt', 200);
            $table->string('credit', 200)->nullable();
            $table->string('license', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
            $table->index(['listing_id', 'sort_order']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('icon', 60)->nullable();
            $table->string('applies_to', 20);                    // stay | dining | activity
        });

        Schema::create('amenity_listing', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->primary(['amenity_id', 'listing_id']);
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_listing');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('activity_details');
        Schema::dropIfExists('dining_details');
        Schema::dropIfExists('stay_details');
        Schema::dropIfExists('listings');
    }
};
