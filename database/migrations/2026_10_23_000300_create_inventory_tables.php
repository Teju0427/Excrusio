<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Room types for stays. `quantity` = number of identical rooms of this type.
        Schema::create('stay_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 400)->nullable();
            $table->string('bed_configuration', 120)->nullable();
            $table->unsignedSmallInteger('max_guests');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('price_per_night');          // paise
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['listing_id', 'is_active']);
        });

        // Weekly opening rules for dining / activities. Used to generate time_slots.
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');              // 0 = Sunday … 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('interval_minutes');    // gap between slot starts
            $table->unsignedSmallInteger('slot_length_minutes');
            $table->unsignedSmallInteger('capacity');            // seats / participants per slot
            $table->timestamps();
            $table->index(['listing_id', 'weekday']);
        });

        // Concrete bookable sessions (a 19:30 table, a 09:00 ski lesson).
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedInteger('price')->nullable();        // paise; overrides listing base price
            $table->string('status', 20)->default('open');       // open | closed | cancelled
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'starts_at']);
            $table->index(['listing_id', 'status', 'starts_at']);
        });

        // Seasonal / weekend prices for a stay unit.
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->date('start_date');
            $table->date('end_date');                            // inclusive
            $table->unsignedTinyInteger('weekday_mask')->default(127); // bit 0 = Sunday … bit 6 = Saturday
            $table->unsignedInteger('price');                    // paise per night
            $table->timestamps();
            $table->index(['stay_unit_id', 'start_date', 'end_date']);
        });

        // Closures and rooms taken out of service by the provider.
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');                            // exclusive, like check-out
            $table->unsignedSmallInteger('rooms')->nullable();   // null = whole unit/listing closed
            $table->string('reason', 200)->nullable();
            $table->timestamps();
            $table->index(['stay_unit_id', 'start_date', 'end_date']);
            $table->index(['listing_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('stay_units');
    }
};
