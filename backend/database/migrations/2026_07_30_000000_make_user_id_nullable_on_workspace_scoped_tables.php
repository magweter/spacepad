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
 *
 * Two things make this messier than a plain column change, both visible on any database
 * restored from a mysqldump (which imports with FOREIGN_KEY_CHECKS=0):
 *
 *  - A table can carry more than one foreign key on `user_id` — the one Laravel named,
 *    plus an auto-named duplicate the restore left behind. Dropping only the conventional
 *    name leaves the duplicate's CASCADE in place and the migration achieves nothing.
 *  - Rows can point at users that no longer exist. The old cascade never saw them, but
 *    adding the constraint back validates every row and fails with a 1452.
 *
 * So: drop *every* foreign key on the column, and detach the dangling rows before
 * reattaching the constraint. Each step is skipped when already applied, so a run that
 * died halfway (MariaDB commits each ALTER separately) can simply be repeated.
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

            $this->dropUserForeignKeys($table);
            $this->setNullable($table, nullable: true);
            $this->detachOrphanedRows($table);
            $this->addUserForeignKey($table, nullable: true);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $this->dropUserForeignKeys($table);

            // Rows orphaned while the column was nullable cannot be restored to a real
            // user, so they are removed rather than left to break the NOT NULL constraint.
            DB::table($table)->whereNull('user_id')->delete();

            $this->setNullable($table, nullable: false);
            $this->addUserForeignKey($table, nullable: false);
        }
    }

    /**
     * Drop every foreign key defined on user_id, whatever it happens to be called.
     */
    private function dropUserForeignKeys(string $table): void
    {
        $keys = collect(Schema::getForeignKeys($table))
            ->filter(fn (array $key) => $key['columns'] === ['user_id']);

        if ($keys->isEmpty()) {
            return;
        }

        $names = $keys->pluck('name')->filter()->values();

        Schema::table($table, function (Blueprint $blueprint) use ($names) {
            // SQLite does not name its foreign keys; the column form rebuilds the table.
            if ($names->isEmpty()) {
                $blueprint->dropForeign(['user_id']);

                return;
            }

            foreach ($names as $name) {
                $blueprint->dropForeign($name);
            }
        });
    }

    private function setNullable(string $table, bool $nullable): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($nullable) {
            $column = $blueprint->ulid('user_id');

            if ($nullable) {
                $column->nullable();
            }

            $column->change();
        });
    }

    /**
     * Point rows whose creator no longer exists at nobody.
     *
     * Null is the honest value here and the only one the new constraint accepts: the row
     * belongs to the workspace, and its creator is gone. Deleting them instead would take
     * live calendars and displays with it.
     */
    private function detachOrphanedRows(string $table): void
    {
        $detached = DB::table($table)
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', fn ($query) => $query->select('id')->from('users'))
            ->update(['user_id' => null]);

        if ($detached > 0) {
            logger()->warning('Detached rows referencing a deleted user', [
                'table' => $table,
                'rows' => $detached,
            ]);
        }
    }

    private function addUserForeignKey(string $table, bool $nullable): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($nullable) {
            $foreign = $blueprint->foreign('user_id')->references('id')->on('users');

            $nullable ? $foreign->nullOnDelete() : $foreign->cascadeOnDelete();
        });
    }
};
