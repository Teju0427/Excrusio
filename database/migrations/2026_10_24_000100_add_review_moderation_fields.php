<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('rejection_reason', 255)->nullable()->after('status');
            $table->text('host_reply')->nullable()->after('body');
            $table->timestamp('host_replied_at')->nullable()->after('host_reply');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['rejection_reason', 'host_reply', 'host_replied_at']);
        });
    }
};
