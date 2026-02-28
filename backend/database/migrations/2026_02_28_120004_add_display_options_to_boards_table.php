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
        Schema::table('boards', function (Blueprint $table) {
            $table->boolean('show_title')->default(true)->after('logo');
            $table->boolean('show_booker')->default(true)->after('show_title');
            $table->boolean('show_next_event')->default(true)->after('show_booker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn(['show_title', 'show_booker', 'show_next_event']);
        });
    }
};
