<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point existing Lemon Squeezy records at workspaces instead of users.
 *
 * This rewrites live billing rows, so it keeps the previous values in
 * legacy_billable_id / legacy_billable_type. That makes down() an exact restore rather than
 * a guess — worth three nullable columns on a table with hundreds of rows.
 *
 * Cloud-only: LemonSqueezy::ignoreMigrations() is set when self-hosted, so these tables do
 * not exist there.
 */
return new class extends Migration
{
    private const TABLES = [
        'lemon_squeezy_subscriptions',
        'lemon_squeezy_customers',
        'lemon_squeezy_orders',
    ];

    public function up(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'legacy_billable_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->ulid('legacy_billable_id')->nullable()->after('billable_type');
                    $blueprint->string('legacy_billable_type')->nullable()->after('legacy_billable_id');
                });
            }

            $this->repoint($table);
        }
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'legacy_billable_id')) {
                continue;
            }

            DB::table($table)->whereNotNull('legacy_billable_id')->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'billable_id' => $row->legacy_billable_id,
                        'billable_type' => $row->legacy_billable_type,
                        'legacy_billable_id' => null,
                        'legacy_billable_type' => null,
                    ]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['legacy_billable_id', 'legacy_billable_type']);
            });
        }
    }

    /**
     * Move every user-owned row in a table onto that user's billing workspace.
     */
    private function repoint(string $table): void
    {
        $workspaceType = (new Workspace)->getMorphClass();
        $userType = (new User)->getMorphClass();

        DB::table($table)
            ->where('billable_type', $userType)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $workspaceType, $userType) {
                foreach ($rows as $row) {
                    $workspaceId = $this->billingWorkspaceId($row->billable_id);

                    if (! $workspaceId) {
                        // Should not happen — every user gets a workspace on creation — but
                        // leave the row alone and make it findable rather than guessing.
                        logger()->warning('Lemon Squeezy row has no workspace to move to', [
                            'table' => $table,
                            'row_id' => $row->id,
                            'user_id' => $row->billable_id,
                        ]);

                        continue;
                    }

                    // lemon_squeezy_customers is unique on (billable_id, billable_type), so
                    // two co-owners each with a customer row would collide. Leave the loser
                    // pointed at the user and log it; a migration must not delete billing rows.
                    if ($table === 'lemon_squeezy_customers'
                        && DB::table($table)
                            ->where('billable_id', $workspaceId)
                            ->where('billable_type', $workspaceType)
                            ->exists()) {
                        logger()->warning('Workspace already has a Lemon Squeezy customer; leaving duplicate on the user', [
                            'row_id' => $row->id,
                            'user_id' => $row->billable_id,
                            'workspace_id' => $workspaceId,
                        ]);

                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'billable_id' => $workspaceId,
                        'billable_type' => $workspaceType,
                        'legacy_billable_id' => $row->billable_id,
                        'legacy_billable_type' => $userType,
                    ]);
                }
            });
    }

    /**
     * The workspace that should carry a user's billing: the one already recorded as their
     * billing workspace, else their earliest owned workspace.
     */
    private function billingWorkspaceId(string $userId): ?string
    {
        $explicit = DB::table('workspaces')->where('billing_owner_user_id', $userId)->orderBy('id')->value('id');

        if ($explicit) {
            return $explicit;
        }

        return DB::table('workspace_members')
            ->where('user_id', $userId)
            ->where('role', 'owner')
            ->orderBy('created_at')
            ->value('workspace_id')
            ?? DB::table('workspace_members')
                ->where('user_id', $userId)
                ->orderBy('created_at')
                ->value('workspace_id');
    }
};
