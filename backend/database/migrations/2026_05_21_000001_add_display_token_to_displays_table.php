<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('displays', function (Blueprint $table) {
            $table->string('display_token', 32)->nullable()->unique()->after('last_event_at');
        });

        // Generate tokens for existing displays
        \App\Models\Display::whereNull('display_token')->each(function ($display) {
            $display->updateQuietly(['display_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('displays', function (Blueprint $table) {
            $table->dropColumn('display_token');
        });
    }
};
