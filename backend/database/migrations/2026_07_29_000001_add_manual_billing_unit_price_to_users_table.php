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
        Schema::table('users', function (Blueprint $table) {
            // Per-account override of the global MANUAL_BILLING_UNIT_PRICE.
            // Null means "use the global default".
            $table->decimal('manual_billing_unit_price', 10, 2)->nullable()->after('is_manually_billed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manual_billing_unit_price');
        });
    }
};
