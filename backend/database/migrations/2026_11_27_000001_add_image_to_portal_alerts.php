<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An alert can carry an image, shown on top of its popup (a screenshot of something new, say).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_alerts', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('portal_alerts', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
