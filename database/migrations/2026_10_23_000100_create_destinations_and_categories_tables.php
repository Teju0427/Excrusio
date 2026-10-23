<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('local_name', 60)->nullable();      // e.g. 京都 (feature F6)
            $table->string('country', 80);
            $table->string('region', 120)->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('summary', 300)->nullable();
            $table->string('hero_image')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Alternative names used by search: Nihon, Nippon, 日本, Bangalore, Kodagu ...
        Schema::create('destination_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 80);
            $table->unique(['destination_id', 'alias']);
            $table->index('alias');
        });

        // Sub-types managed by admins, e.g. Villa, Fine dining, Skiing.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type', 20);                 // stay | dining | activity
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('icon', 60)->nullable();             // Bootstrap Icons class
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['listing_type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('destination_aliases');
        Schema::dropIfExists('destinations');
    }
};
