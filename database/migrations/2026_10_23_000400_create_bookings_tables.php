<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One checkout that contains several bookings (feature F1: stay + dinner + activity).
        Schema::create('booking_groups', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('total');                    // paise
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();           // e.g. EXC-ST-7K3P9Q
            $table->string('source', 10)->default('online');     // online | offline (phone / walk-in)
            $table->foreignId('booking_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->string('listing_type', 20);
            $table->foreignId('stay_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('time_slot_id')->nullable()->constrained()->restrictOnDelete();

            // Stays use dates; dining/activities copy their slot times here for easy querying.
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('nights')->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->unsignedSmallInteger('guests');              // guests or participants

            $table->string('guest_name', 120);
            $table->string('guest_email', 160)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->text('special_requests')->nullable();
            $table->timestamp('eligibility_acknowledged_at')->nullable();

            // Price snapshot, always calculated on the server (paise).
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('fees')->default(0);
            $table->unsignedInteger('total');
            $table->json('price_breakdown')->nullable();

            $table->string('cancellation_policy', 20);           // copied from listing at booking time
            $table->string('status', 20);                        // see App\Enums\BookingStatus
            $table->string('payment_status', 20);                // see App\Enums\PaymentStatus
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('response_due_at')->nullable();    // hotel must answer request by this time
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();
            $table->unsignedInteger('refund_amount')->nullable();

            // QR check-in (feature F3)
            $table->string('checkin_token', 64)->nullable()->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            // Overlap check for stays and capacity check for slots.
            $table->index(['stay_unit_id', 'status', 'check_in', 'check_out']);
            $table->index(['time_slot_id', 'status']);
            $table->index(['customer_id', 'status', 'starts_at']);
            $table->index(['listing_id', 'status', 'starts_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('booking_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamps();
        });

        // Payment attempts are kept separate from booking totals.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gateway', 30)->default('demo');
            $table->string('gateway_reference', 100)->nullable();
            $table->string('type', 20)->default('charge');       // charge | refund
            $table->unsignedInteger('amount');                   // paise
            $table->string('status', 20);                        // succeeded | failed
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'type']);
        });

        // Feature F2: waitlist for full slots.
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('party_size');
            $table->string('status', 20)->default('waiting');    // waiting | offered | claimed | expired | left
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['time_slot_id', 'user_id']);
            $table->index(['time_slot_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_participants');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_groups');
    }
};
