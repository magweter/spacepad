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
        Schema::create('panel_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('panel_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('display_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['panel_id', 'display_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_displays');
    }
};

