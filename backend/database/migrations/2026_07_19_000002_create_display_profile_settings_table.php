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
        Schema::create('display_profile_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('display_profile_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value');
            $table->string('type')->default('string');
            $table->timestamps();

            // Ensure unique settings per profile
            $table->unique(['display_profile_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('display_profile_settings');
    }
};
