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
        Schema::table('calendars', function (Blueprint $table) {
            $table->foreignUlid('google_account_id')->nullable()->after('outlook_account_id')->constrained()->onDelete('cascade'); // Link to GoogleAccount
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->dropForeign(['outlook_account_id']);
            $table->dropColumn('outlook_account_id');
        });
    }
};
