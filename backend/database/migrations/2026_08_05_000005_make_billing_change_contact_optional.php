<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a billing change outlive the person named on it.
 *
 * The trail belongs to the workspace: it records what a workspace was charged for and
 * when. The user columns are only "who would we have emailed about this", denormalised at
 * the time. Deleting an account used to delete their rows outright, which quietly tore
 * holes in a shared workspace's history because one colleague closed their account.
 *
 * Nullable instead, so the personal detail can be scrubbed while the change itself
 * remains. Also covers a workspace with no members left to name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->ulid('user_id')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted') || ! Schema::hasTable('billing_changes')) {
            return;
        }

        // The old shape has no way to say "scrubbed", so an empty string is the closest it
        // gets. Rows with no user at all cannot be represented and are dropped.
        DB::table('billing_changes')->whereNull('user_id')->delete();
        DB::table('billing_changes')->whereNull('email')->update(['email' => '']);
        DB::table('billing_changes')->whereNull('name')->update(['name' => '']);

        Schema::table('billing_changes', function (Blueprint $table) {
            $table->ulid('user_id')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
        });
    }
};
