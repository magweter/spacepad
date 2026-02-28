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
        Schema::create('board_displays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('board_id')->constrained('boards')->onDelete('cascade');
            $table->foreignUlid('display_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['board_id', 'display_id']);
            $table->index('board_id');
            $table->index('display_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_displays');
    }
};
