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
            $table->foreignUlid('google_account_id')->nullable()->after('outlook_account_id')
                ->constrained()->onDelete('cascade');
        });
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->foreignUlid('outlook_account_id')->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['google_account_id']);
            $table->dropColumn('google_account_id');
        });
    }
};
