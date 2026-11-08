<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When someone finished (or skipped) the onboarding wizard.
 *
 * The wizard connects a calendar halfway through, which used to be the end of onboarding. This
 * marks the real end, so the wizard can carry on after that. Everyone who is already past the
 * old onboarding counts as done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('skipped_onboarding_at');
        });

        DB::table('users')
            ->where(fn ($query) => $query->whereNotNull('usage_type')->orWhereNotNull('skipped_onboarding_at'))
            ->update(['onboarding_completed_at' => DB::raw('COALESCE(skipped_onboarding_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
