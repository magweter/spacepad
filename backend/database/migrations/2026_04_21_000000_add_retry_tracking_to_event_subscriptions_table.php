<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('notification_url');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->timestamp('next_retry_at')->nullable()->after('last_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_retry_at', 'next_retry_at']);
        });
    }
};
