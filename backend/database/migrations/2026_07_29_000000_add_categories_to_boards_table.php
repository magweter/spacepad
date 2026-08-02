<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            // Ordered list of room categories for this board:
            // [{"name": "Floor 1", "display_ids": ["01J...", "01J..."]}, ...]
            // Array order is the display order on the board.
            $table->json('categories')->nullable()->after('view_mode');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
