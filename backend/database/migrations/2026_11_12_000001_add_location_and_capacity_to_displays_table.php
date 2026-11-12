<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('displays', function (Blueprint $table) {
            // Printed under the room name on the tablet, e.g. "Floor 1, 12 seats". Both optional:
            // a display without them shows the room name alone, as before.
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('displays', function (Blueprint $table) {
            $table->dropColumn(['location', 'capacity']);
        });
    }
};
