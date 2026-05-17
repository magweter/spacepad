<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->string('booking_method')->nullable()->after('permission_type');
        });
    }

    public function down(): void
    {
        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->dropColumn('booking_method');
        });
    }
};
