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
        // Add workspace_id to displays
        Schema::table('displays', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Add workspace_id to devices
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Add workspace_id to calendars
        Schema::table('calendars', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Add workspace_id to rooms
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('displays', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('calendars', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }
};

