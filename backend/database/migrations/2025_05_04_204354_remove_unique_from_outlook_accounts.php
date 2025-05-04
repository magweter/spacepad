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
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->dropUnique(['outlook_id']);
            $table->dropUnique(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->unique('outlook_id');
            $table->unique('email');
        });
    }
};
