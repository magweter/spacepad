<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the analytics snapshot count each workspace's usage exactly once.
 *
 * The table held one row per user, but every workspace-derived figure on it — displays,
 * boards, subscription, MRR — was read from that user's primary workspace. Two colleagues
 * in one workspace therefore produced two rows carrying the same 41 displays and the same
 * MRR, and any SUM over this table counted that customer twice.
 *
 * A row now describes a *membership*: one per (user, workspace). The workspace figures sit
 * on the row of the member who carries the billing (`is_billing_owner`), and are left at
 * zero for everyone else, so existing aggregates become correct without being rewritten.
 * Widening the unique key is what lets a user who bills two workspaces appear once for each
 * instead of silently losing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('analytics_users')) {
            return;
        }

        if (! Schema::hasColumn('analytics_users', 'is_billing_owner')) {
            Schema::table('analytics_users', function (Blueprint $table) {
                $table->boolean('is_billing_owner')->default(false)->after('workspace_name');
            });
        }

        Schema::table('analytics_users', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'workspace_id']);
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('analytics_users')) {
            return;
        }

        // A user can hold several rows now, so collapse to one before the narrow key can
        // come back. The table is a snapshot that the next refresh rebuilds in full.
        Schema::table('analytics_users', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'workspace_id']);
        });

        Schema::table('analytics_users', function (Blueprint $table) {
            $table->dropColumn('is_billing_owner');
        });

        DB::table('analytics_users')->delete();

        Schema::table('analytics_users', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }
};
