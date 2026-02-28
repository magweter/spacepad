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
        Schema::table('instances', function (Blueprint $table) {
            $table->integer('boards_count')->nullable()->after('rooms_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropColumn('boards_count');
        });
    }
};
