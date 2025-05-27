<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->string('status')->default(AccountStatus::CONNECTED)->after('email');
        });

        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->string('status')->default(AccountStatus::CONNECTED)->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('outlook_accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}; 