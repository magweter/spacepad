<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a billing change say "the money side is not known yet".
 *
 * Changes are now recorded the instant usage moves, which is well before anyone has asked
 * Lemon Squeezy what the new subscription is worth. Defaulting those to 0 would put a
 * fictional "MRR dropped to zero" on the trail; null says the truth, and
 * app:refresh-analytics --mrr replaces it with the real figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->decimal('previous_mrr', 10, 2)->nullable()->default(null)->change();
            $table->decimal('new_mrr', 10, 2)->nullable()->default(null)->change();
            $table->decimal('mrr_delta', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        // Unknown becomes zero again, which is the best the old shape can express.
        DB::table('billing_changes')->whereNull('new_mrr')->update(['new_mrr' => 0]);
        DB::table('billing_changes')->whereNull('previous_mrr')->update(['previous_mrr' => 0]);
        DB::table('billing_changes')->whereNull('mrr_delta')->update(['mrr_delta' => 0]);

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->decimal('previous_mrr', 10, 2)->default(0)->change();
            $table->decimal('new_mrr', 10, 2)->default(0)->change();
            $table->decimal('mrr_delta', 10, 2)->default(0)->change();
        });
    }
};
