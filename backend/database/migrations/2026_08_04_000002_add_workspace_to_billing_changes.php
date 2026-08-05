<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which workspace's licence count changed, not just who was told about it.
 *
 * Usage is charged per workspace, so that is the thing whose count moves. The user columns
 * stay: they say who to contact, which is the billing owner at the moment of detection.
 * Without the workspace on the row, handing billing to a colleague reads as one account
 * dropping to zero and a second one appearing — two alerts for a change that never happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        if (Schema::hasColumn('billing_changes', 'workspace_id')) {
            return;
        }

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->ulid('workspace_id')->nullable()->after('user_id');
            $table->string('workspace_name')->nullable()->after('workspace_id');

            $table->index(['workspace_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'detected_at']);
            $table->dropColumn(['workspace_id', 'workspace_name']);
        });
    }
};
