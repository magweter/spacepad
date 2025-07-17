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
        Schema::create('display_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('display_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value');
            $table->string('type')->default('string');
            $table->timestamps();
            
            // Ensure unique settings per display
            $table->unique(['display_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('display_settings');
    }
}; 