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
            $table->boolean('show_transitioning')->default(true)->after('show_next_event');
            $table->integer('transitioning_minutes')->default(10)->after('show_transitioning');
            $table->string('font_family')->default('Inter')->after('transitioning_minutes');
            $table->string('language')->default('en')->after('font_family');
            $table->boolean('show_meeting_title')->default(true)->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn([
                'show_transitioning',
                'transitioning_minutes',
                'font_family',
                'language',
                'show_meeting_title',
            ]);
        });
    }
};
