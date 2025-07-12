<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('display_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('calendar_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('status');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->text('summary')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('timezone');
            $table->string('source');
            $table->string('external_id')->nullable();

            // Check-in functionality
            $table->timestamp('checked_in_at')->nullable();

            // Audit logging
            $table->timestamps();

            // Indexes for performance
            $table->index(['display_id', 'start', 'end']);
            $table->index(['external_id', 'source']);
            $table->index(['calendar_id', 'start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
