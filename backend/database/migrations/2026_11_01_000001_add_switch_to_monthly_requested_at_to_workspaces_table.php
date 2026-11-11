<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Set while a yearly workspace waits to go back to monthly at the end of the paid
            // year; cleared by app:process-yearly-subscriptions once the swap went through.
            $table->timestamp('switch_to_monthly_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('switch_to_monthly_requested_at');
        });
    }
};
