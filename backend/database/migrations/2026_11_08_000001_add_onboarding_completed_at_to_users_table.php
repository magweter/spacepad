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

        // Exactly the people the old User::isOnboarded() let through: a usage type, and a calendar
        // (their own or one in a workspace they belong to) or a skip. Someone who only picked a
        // usage type stopped halfway and resumes the wizard. The licence check on self-hosted
        // stays in isOnboarded(), so it is not repeated here.
        $hasCalendar = function ($query) {
            foreach (['outlook_accounts', 'google_accounts', 'caldav_accounts'] as $table) {
                $query->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from($table)->whereColumn("$table.user_id", 'users.id'))
                    ->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from($table)
                        ->join('workspace_members', 'workspace_members.workspace_id', '=', "$table.workspace_id")
                        ->whereColumn('workspace_members.user_id', 'users.id'));
            }
        };

        DB::table('users')
            ->whereNotNull('usage_type')
            ->where(fn ($query) => $query->whereNotNull('skipped_onboarding_at')->orWhere($hasCalendar))
            ->update(['onboarding_completed_at' => DB::raw('COALESCE(skipped_onboarding_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
