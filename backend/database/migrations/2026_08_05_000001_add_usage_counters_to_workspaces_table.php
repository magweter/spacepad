<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The authoritative count of what a workspace is billed for.
 *
 * Usage used to be recounted at every call site, so the team page, the admin screen, the
 * analytics snapshot and the Lemon Squeezy quantity each ran their own COUNT and could
 * disagree with the invoice. These two columns are the single answer, maintained by
 * WorkspaceUsageService and repaired by app:reconcile-workspace-usage.
 *
 * Unlike the analytics tables this is not gated on self-hosted — billable usage is core
 * domain state, not reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('displays_count')->default(0)->after('name');
            $table->unsignedInteger('boards_count')->default(0)->after('displays_count');
        });

        // One grouped subquery per table rather than a row-by-row loop: the backfill runs
        // inside the deploy, and every workspace has to be visited either way.
        foreach (['displays', 'boards'] as $relation) {
            DB::table('workspaces')->update([
                "{$relation}_count" => DB::table($relation)
                    ->selectRaw('count(*)')
                    ->whereColumn("{$relation}.workspace_id", 'workspaces.id'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['displays_count', 'boards_count']);
        });
    }
};
