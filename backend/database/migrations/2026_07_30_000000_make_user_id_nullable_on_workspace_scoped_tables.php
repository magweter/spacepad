<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace-scoped data must survive the departure of the user who created it.
 *
 * These tables were all created with `user_id` NOT NULL + onDelete('cascade'), from a
 * time when a user *was* the tenant. Now that a workspace is the tenant and can have
 * several members, that cascade means one colleague deleting their account takes the
 * whole team's displays, devices and calendar accounts with it.
 *
 * After this migration `user_id` is provenance ("created by"), not ownership: it is
 * nullable and nulls out on delete. `boards` and `display_profiles` already worked this
 * way; this brings the rest in line.
 */
return new class extends Migration
{
    /**
     * Tables carrying a user_id that should become nullable provenance.
     */
    private const TABLES = [
        'displays',
        'devices',
        'rooms',
        'calendars',
        'outlook_accounts',
        'google_accounts',
        'caldav_accounts',
        'events',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $this->repointForeignKey($table, nullable: true);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            // Rows orphaned while the column was nullable cannot be restored to a real
            // user, so they are removed rather than left to break the NOT NULL constraint.
            DB::table($table)->whereNull('user_id')->delete();

            $this->repointForeignKey($table, nullable: false);
        }
    }

    /**
     * Drop the existing user_id foreign key, change the column's nullability, and put the
     * constraint back with the matching delete behaviour.
     */
    private function repointForeignKey(string $table, bool $nullable): void
    {
        // Pass the column rather than the constraint name: SQLite cannot drop a foreign
        // key by name, and on MySQL the column form resolves to the same conventional
        // name the original migrations created.
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['user_id']);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($nullable) {
            $column = $blueprint->ulid('user_id');

            if ($nullable) {
                $column->nullable();
            }

            $column->change();
        });

        Schema::table($table, function (Blueprint $blueprint) use ($nullable) {
            $foreign = $blueprint->foreign('user_id')->references('id')->on('users');

            $nullable ? $foreign->nullOnDelete() : $foreign->cascadeOnDelete();
        });
    }
};
