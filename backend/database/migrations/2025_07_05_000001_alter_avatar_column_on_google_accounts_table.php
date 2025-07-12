<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->text('avatar')->change();
        });
    }

    public function down(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->string('avatar', 255)->change();
        });
    }
};
