<?php

use App\Enums\WorkspaceRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finish the move of billing from the person to the workspace.
 *
 * 2026_07_30_000021 copied the flags down onto the workspaces and everything has read them
 * from there since. What it left behind was the original columns on `users`, still
 * fillable, still cast, and still the first thing anyone would find when looking for
 * "where is the manual price set". Two homes for one fact is how they drift.
 *
 * The backfill here is a safety net, not the main event: it only fills a workspace value
 * that is still empty, so a price an admin deliberately set on the workspace after the
 * earlier migration is never overwritten by the stale one on the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillWhatIsMissing();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_unlimited', 'is_manually_billed', 'manual_billing_unit_price']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('is_manually_billed')->default(false);
            $table->decimal('manual_billing_unit_price', 10, 2)->nullable();
        });

        // Push each workspace's state back onto whoever carries its billing. Not perfectly
        // invertible for a workspace with several owners, which is why the original move
        // logged those cases at the time.
        DB::table('workspaces')
            ->whereNotNull('billing_owner_user_id')
            ->orderBy('id')
            ->chunkById(200, function ($workspaces) {
                foreach ($workspaces as $workspace) {
                    DB::table('users')->where('id', $workspace->billing_owner_user_id)->update([
                        'is_unlimited' => $workspace->is_unlimited,
                        'is_manually_billed' => $workspace->is_manually_billed,
                        'manual_billing_unit_price' => $workspace->manual_billing_unit_price,
                    ]);
                }
            });
    }

    /**
     * Copy anything the earlier backfill could not reach.
     *
     * Reaches a workspace that had no owner membership when 000021 ran, or one created
     * since. Deliberately one-directional: a false stays false only if no owner says
     * otherwise, and a price is taken only where the workspace has none.
     */
    private function backfillWhatIsMissing(): void
    {
        DB::table('workspaces')->orderBy('id')->chunkById(200, function ($workspaces) {
            foreach ($workspaces as $workspace) {
                $owners = DB::table('workspace_members')
                    ->join('users', 'users.id', '=', 'workspace_members.user_id')
                    ->where('workspace_members.workspace_id', $workspace->id)
                    ->where('workspace_members.role', WorkspaceRole::OWNER->value)
                    ->orderBy('workspace_members.created_at')
                    ->orderBy('users.id')
                    ->select('users.*')
                    ->get();

                if ($owners->isEmpty()) {
                    continue;
                }

                $updates = [];

                if (! $workspace->is_unlimited && $owners->contains(fn ($owner) => (bool) $owner->is_unlimited)) {
                    $updates['is_unlimited'] = true;
                }

                if (! $workspace->is_manually_billed && $owners->contains(fn ($owner) => (bool) $owner->is_manually_billed)) {
                    $updates['is_manually_billed'] = true;
                }

                if ($workspace->manual_billing_unit_price === null) {
                    $price = $owners->pluck('manual_billing_unit_price')
                        ->filter(fn ($value) => $value !== null)
                        ->min();

                    if ($price !== null) {
                        $updates['manual_billing_unit_price'] = $price;
                    }
                }

                if ($workspace->billing_owner_user_id === null) {
                    $updates['billing_owner_user_id'] = $owners->first()->id;
                }

                if ($updates !== []) {
                    DB::table('workspaces')->where('id', $workspace->id)->update($updates);
                }
            }
        });
    }
};
