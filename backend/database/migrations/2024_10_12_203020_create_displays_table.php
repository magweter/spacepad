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
        Schema::create('displays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade'); // Link to the user performing the sync
            $table->foreignUlid('calendar_id')->constrained('calendars')->onDelete('cascade'); // Link to the calendar being synced
            $table->string('name');
            $table->string('display_name');
            $table->string('status')->nullable();
            $table->timestamp('last_sync_at', 6)->nullable();
            $table->timestamp('last_event_at', 6)->nullable();
            $table->timestamps(); // Laravel default: created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('displays');
    }
};
