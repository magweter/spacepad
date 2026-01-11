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
        // Add workspace_id to outlook_accounts
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Add workspace_id to google_accounts
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Add workspace_id to caldav_accounts
        Schema::table('caldav_accounts', function (Blueprint $table) {
            $table->foreignUlid('workspace_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('google_accounts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('caldav_accounts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }
};

