<?php

namespace App\Console\Commands;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only report on what moving billing to workspaces would change.
 *
 * Run this against production and resolve the findings by hand *before* running the
 * repoint migration. Two of them need a decision rather than code:
 *
 *  - a user with a subscription and several owned workspaces keeps Pro on only one
 *  - the corrected usage figure is lower for anyone who had joined a second workspace,
 *    which lowers their next invoice
 */
class AuditBillingMove extends Command
{
    protected $signature = 'app:audit-billing-move';

    protected $description = 'Report what moving billing from users to workspaces would change (read-only)';

    public function handle(): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Self-hosted instance: billing lives in the licence, nothing to audit.');

            return self::SUCCESS;
        }

        $this->multipleOwnedWorkspaces();
        $this->disagreeingOwners();
        $this->usersWithoutWorkspace();
        $this->customerCollisions();
        $this->usageDelta();

        return self::SUCCESS;
    }

    /**
     * Subscribers who own more than one workspace: the subscription can only land on one,
     * so the others lose Pro.
     */
    private function multipleOwnedWorkspaces(): void
    {
        $this->info(PHP_EOL.'== Subscribers owning more than one workspace ==');

        $affected = User::query()
            ->whereHas('subscriptions', fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->withCount(['workspaces as owned_count' => fn ($q) => $q->wherePivot('role', WorkspaceRole::OWNER->value)])
            ->get()
            ->filter(fn ($user) => $user->owned_count > 1);

        if ($affected->isEmpty()) {
            $this->line('None.');

            return;
        }

        foreach ($affected as $user) {
            $this->warn("{$user->email} owns {$user->owned_count} workspaces — all but one will lose Pro.");
        }

        $this->warn('Resolve these by hand (consolidate, or set is_unlimited on the extras) before migrating.');
    }

    /**
     * Workspaces whose owners hold different billing flags, so the backfill has to pick.
     */
    private function disagreeingOwners(): void
    {
        $this->info(PHP_EOL.'== Workspaces with owners that disagree on billing flags ==');

        $found = false;

        Workspace::with(['members' => fn ($q) => $q->wherePivot('role', WorkspaceRole::OWNER->value)])
            ->chunk(200, function ($workspaces) use (&$found) {
                foreach ($workspaces as $workspace) {
                    if ($workspace->members->count() < 2) {
                        continue;
                    }

                    $unlimited = $workspace->members->pluck('is_unlimited')->unique();
                    $manual = $workspace->members->pluck('is_manually_billed')->unique();

                    if ($unlimited->count() > 1 || $manual->count() > 1) {
                        $found = true;
                        $this->warn("Workspace {$workspace->id} ({$workspace->name}): owners disagree; the backfill ORs them together.");
                    }
                }
            });

        if (! $found) {
            $this->line('None.');
        }
    }

    /**
     * Billing rows belonging to a user with no workspace at all.
     */
    private function usersWithoutWorkspace(): void
    {
        $this->info(PHP_EOL.'== Billing rows whose user has no workspace ==');

        $found = User::query()
            ->whereDoesntHave('workspaces')
            ->where(function ($query) {
                $query->whereHas('subscriptions')->orWhereHas('customer');
            })
            ->get();

        if ($found->isEmpty()) {
            $this->line('None.');

            return;
        }

        foreach ($found as $user) {
            $this->error("{$user->email} has billing records but no workspace — the repoint will skip and log these.");
        }
    }

    /**
     * Workspaces where more than one owner has a Lemon Squeezy customer row. Only one can
     * move, because the customers table is unique on (billable_id, billable_type).
     */
    private function customerCollisions(): void
    {
        $this->info(PHP_EOL.'== Workspaces where several owners have a Lemon Squeezy customer ==');

        if (! Schema::hasTable('lemon_squeezy_customers')) {
            $this->line('Table not present.');

            return;
        }

        $found = false;

        Workspace::with(['members' => fn ($q) => $q->wherePivot('role', WorkspaceRole::OWNER->value)])
            ->chunk(200, function ($workspaces) use (&$found) {
                foreach ($workspaces as $workspace) {
                    $ownerIds = $workspace->members->pluck('id');

                    if ($ownerIds->count() < 2) {
                        continue;
                    }

                    $customers = DB::table('lemon_squeezy_customers')
                        ->whereIn('billable_id', $ownerIds)
                        ->where('billable_type', (new User)->getMorphClass())
                        ->count();

                    if ($customers > 1) {
                        $found = true;
                        $this->warn("Workspace {$workspace->id}: {$customers} owner customer rows; only one can move, the rest stay on the user and are logged.");
                    }
                }
            });

        if (! $found) {
            $this->line('None.');
        }
    }

    /**
     * The commercially significant one: old usage (all of a user's memberships summed)
     * versus new usage (their own workspace only).
     */
    private function usageDelta(): void
    {
        $this->info(PHP_EOL.'== Usage per subscription: old rule vs new rule ==');

        $users = User::query()
            ->whereHas('subscriptions', fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('workspaces')
            ->get();

        if ($users->isEmpty()) {
            $this->line('No active subscriptions.');

            return;
        }

        $totalOld = 0;
        $totalNew = 0;

        foreach ($users as $user) {
            $old = $user->workspaces->sum(fn ($workspace) => $workspace->getTotalUsageCount());

            $billing = Workspace::where('billing_owner_user_id', $user->id)->first()
                ?? $user->ownedWorkspaces()->orderByPivot('created_at')->first();

            $new = $billing?->getTotalUsageCount() ?? 0;

            $totalOld += $old;
            $totalNew += $new;

            if ($old !== $new) {
                $this->warn(sprintf('%s: %d -> %d units (%+d)', $user->email, $old, $new, $new - $old));
            }
        }

        $this->info(sprintf('Totals: %d -> %d units (%+d).', $totalOld, $totalNew, $totalNew - $totalOld));

        if ($totalNew < $totalOld) {
            $this->warn('The new figure is lower, so some invoices will drop. Get sign-off before enabling the usage push.');
        }
    }
}
