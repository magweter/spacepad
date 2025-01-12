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
        Schema::create('calendars', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade'); // Link to the user who owns the calendar
            $table->foreignUlid('outlook_account_id')->nullable()->constrained()->onDelete('cascade'); // Link to OutlookAccount
            $table->string('calendar_id')->unique(); // External calendar ID
            $table->string('name'); // Name of the calendar (e.g., "Work", "Personal")
            $table->boolean('is_primary')->default(false); // Whether it's the user's primary calendar
            $table->timestamps(); // Laravel default: created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
