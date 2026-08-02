<?php

namespace App\Listeners\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Repairing Lemon Squeezy rows that still arrive addressed to a user.
 *
 * The vendor webhook controller resolves the billable from `meta.custom_data`, which was
 * baked in when the checkout was created. Every subscription that existed before billing
 * moved therefore says `App\Models\User` forever, and each renewal makes the package
 * re-create a user-addressed customer row.
 *
 * These listeners move such rows back onto the workspace. The warning they log is the
 * signal for when the legacy window can be closed: once it stops appearing, the Billable
 * trait can come off User.
 */
trait RemapsLegacyBillable
{
    /**
     * Point a freshly written row at the billable's workspace instead of the user.
     */
    protected function remap(?Model $row, mixed $billable): void
    {
        if (! $row || ! $billable instanceof User) {
            return;
        }

        $workspace = $this->billingWorkspaceFor($billable);

        if (! $workspace) {
            return;
        }

        $row->forceFill([
            'billable_id' => $workspace->id,
            'billable_type' => $workspace->getMorphClass(),
        ])->save();

        logger()->warning('Remapped a legacy user-addressed Lemon Squeezy record to its workspace', [
            'record' => $row::class,
            'record_id' => $row->getKey(),
            'user_id' => $billable->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Same rule as the repoint migration: the recorded billing workspace, else the earliest
     * owned one.
     */
    protected function billingWorkspaceFor(User $user): ?Workspace
    {
        return Workspace::where('billing_owner_user_id', $user->id)->orderBy('id')->first()
            ?? $user->ownedWorkspaces()->orderByPivot('created_at')->first()
            ?? $user->primaryWorkspace();
    }
}
