<?php

use App\Enums\WorkspaceRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copy each workspace's billing state down from its owners.
 *
 * Raw queries on purpose: no models, no events, no casts, so the backfill cannot be
 * changed by later application code.
 */
return new class extends Migration
{
    public function up(): void
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

                $billingOwner = $owners->first();

                // OR across owners, so nobody loses access they had a moment ago.
                $isUnlimited = $owners->contains(fn ($owner) => (bool) $owner->is_unlimited);
                $isManual = $owners->contains(fn ($owner) => (bool) $owner->is_manually_billed);

                // Deterministic: the billing owner's price. If they have none but a
                // co-owner does, take the lowest so nobody is charged more than before.
                $price = $billingOwner->manual_billing_unit_price;

                if ($price === null) {
                    $price = $owners->pluck('manual_billing_unit_price')->filter(fn ($p) => $p !== null)->min();
                }

                if ($owners->count() > 1) {
                    $disagree = $owners->pluck('is_unlimited')->unique()->count() > 1
                        || $owners->pluck('is_manually_billed')->unique()->count() > 1;

                    if ($disagree) {
                        logger()->warning('Workspace owners disagreed on billing flags during backfill', [
                            'workspace_id' => $workspace->id,
                            'owner_ids' => $owners->pluck('id')->all(),
                        ]);
                    }
                }

                DB::table('workspaces')->where('id', $workspace->id)->update([
                    'is_unlimited' => $isUnlimited,
                    'is_manually_billed' => $isManual,
                    'manual_billing_unit_price' => $price,
                    'billing_owner_user_id' => $billingOwner->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Push the workspace's state back onto its billing owner. Not perfectly invertible
        // for a workspace that had several owners disagreeing — the OR above collapsed that
        // — which is why up() logs those cases.
        DB::table('workspaces')->whereNotNull('billing_owner_user_id')
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
};
