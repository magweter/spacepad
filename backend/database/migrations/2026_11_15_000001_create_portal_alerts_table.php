<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alerts an admin sets by hand, such as a calendar provider outage, shown in the portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 120);
            $table->string('body', 500)->nullable();
            $table->string('tone')->default('attention');
            $table->string('provider')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_alerts');
    }
};
