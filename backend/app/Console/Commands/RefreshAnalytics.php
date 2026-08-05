<?php

namespace App\Console\Commands;

use App\Enums\WorkspaceRole;
use App\Models\Instance;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RefreshAnalytics extends Command
{
    protected $signature = 'app:refresh-analytics {--mrr : Also refresh MRR data from LemonSqueezy API}';

    protected $description = 'Refresh analytics_users and analytics_instances snapshot tables for Metabase';

    public function handle(): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Skipping analytics refresh - self-hosted instance.');

            return self::SUCCESS;
        }

        // The default run and the --mrr run share one application-level lock so a fast
        // (MRR-preserving) run can never overlap an --mrr run and clobber freshly fetched
        // MRR values with the stale ones it carried forward. The per-command scheduler
        // mutex can't do this because it keys on the command + arguments, giving the two
        // invocations separate locks.
        $lock = Cache::lock('refresh-analytics', 600);

        if (! $lock->get()) {
            $this->info('Another analytics refresh is already running - skipping.');

            return self::SUCCESS;
        }

        try {
            $withMrr = $this->option('mrr');
            $this->info('Refreshing analytics tables'.($withMrr ? ' (including MRR)' : '').'...');

            $this->refreshUsers($withMrr);
            $this->refreshInstances($withMrr);

            $this->info('Analytics refresh completed.');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function refreshUsers(bool $withMrr): void
    {
        $now = now();

        // Last run's figures, keyed by workspace. Both the MRR carried over on a fast run and
        // the licence-change detection key on the workspace rather than on a user: the
        // subscription belongs to the workspace, so handing billing to a colleague is not a
        // change in usage and must not be reported as one.
        //
        // Deliberately not filtered to is_billing_owner. The first run after this became a
        // per-membership snapshot finds that flag false on every row, and filtering on it
        // would carry nothing over and reset every subscription's MRR to zero until an --mrr
        // run happened to succeed. Ordering ascending instead means the owner's row is the
        // last one keyBy() sees, so it wins wherever one exists.
        $previous = DB::table('analytics_users')
            ->whereNotNull('workspace_id')
            ->orderBy('is_billing_owner')
            ->select('workspace_id', 'displays_count', 'boards_count', 'mrr_current', 'mrr_expected', 'billing_interval')
            ->get()
            ->keyBy('workspace_id');

        $changes = [];
        $rowCount = 0;

        // Walking workspaces rather than users is what keeps usage counted once: each
        // workspace is visited exactly once and its figures land on exactly one member.
        // Chunked so memory stays flat as the table grows.
        Workspace::query()
            ->withCount(['displays', 'boards', 'rooms'])
            ->withMax('devices', 'last_activity_at')
            ->with([
                'members' => fn ($q) => $q->whereNull('users.deleted_at')->orderByPivot('created_at'),
                'billingOwnerUser',
                'subscriptions' => fn ($q) => $q
                    ->where(fn ($sub) => $sub->whereNull('ends_at')->orWhere('ends_at', '>', $now))
                    ->orderByDesc('created_at'),
            ])
            ->chunkById(200, function ($workspaces) use (&$changes, &$rowCount, $withMrr, $now, $previous) {
                $rows = [];

                foreach ($workspaces as $workspace) {
                    $snapshot = $previous->get($workspace->id);
                    $billingUser = $this->resolveBillingUser($workspace);
                    $billing = $this->resolveBilling($workspace, $withMrr, $snapshot);

                    if ($change = $this->detectLicenceChange($workspace, $billing, $billingUser, $snapshot, $now)) {
                        $changes[] = $change;
                    }

                    foreach ($workspace->members as $member) {
                        $rows[] = $this->userRow(
                            $member,
                            $workspace,
                            $member->id === $billingUser?->id ? $billing : null,
                            $now
                        );
                    }
                }

                $rowCount += count($rows);
                $this->upsertUserRows($rows);
            });

        // Someone who belongs to no workspace still deserves a row: they registered, there
        // is simply nothing to bill yet.
        User::query()
            ->whereNull('deleted_at')
            ->whereDoesntHave('workspaces')
            ->chunkById(500, function ($users) use (&$rowCount, $now) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = $this->userRow($user, null, null, $now);
                }

                $rowCount += count($rows);
                $this->upsertUserRows($rows);
            });

        // Whatever this run did not stamp belongs to a user or workspace that is gone. Only
        // prune when something was seen: after a failed run the timestamp filter would
        // otherwise match every row and wipe the snapshot.
        if ($rowCount > 0) {
            DB::table('analytics_users')->where('refreshed_at', '<', $now)->delete();
        }

        $this->line("Upserted {$rowCount} user rows.");

        foreach (array_chunk($changes, 100) as $chunk) {
            DB::table('billing_changes')->insert($chunk);
        }

        if ($changes !== []) {
            $this->line('Recorded '.count($changes).' billing change(s).');
        }
    }

    /**
     * The member who carries this workspace's billing.
     *
     * Prefers the recorded billing owner, but only while they are still a member: a stale
     * pointer would otherwise leave the workspace's usage attributed to nobody and quietly
     * drop it out of every total. Falls back to the earliest owner, then to any member.
     */
    private function resolveBillingUser(Workspace $workspace): ?User
    {
        $members = $workspace->members;

        if ($workspace->billing_owner_user_id) {
            $recorded = $members->firstWhere('id', $workspace->billing_owner_user_id);

            if ($recorded) {
                return $recorded;
            }
        }

        return $members->first(fn (User $member) => WorkspaceRole::fromPivot($member->pivot->role) === WorkspaceRole::OWNER)
            ?? $members->first();
    }

    /**
     * What this workspace uses, what it is on, and what it is worth per month.
     *
     * @return array<string, mixed>
     */
    private function resolveBilling(Workspace $workspace, bool $withMrr, ?object $previous): array
    {
        $displays = (int) ($workspace->displays_count ?? 0);
        $boards = (int) ($workspace->boards_count ?? 0);

        $billing = [
            'displays_count' => $displays,
            'boards_count' => $boards,
            'rooms_count' => (int) ($workspace->rooms_count ?? 0),
            'is_unlimited' => (bool) $workspace->is_unlimited,
            'subscription_status' => 'none',
            'billing_interval' => $previous->billing_interval ?? null,
            'trial_ends_at' => null,
            'subscription_ends_at' => null,
            'subscription_renews_at' => null,
            'lemon_squeezy_id' => null,
            // Carried over on a fast run so a non-MRR refresh cannot clobber the figures an
            // --mrr run fetched from the API.
            'mrr_current' => (float) ($withMrr ? 0 : ($previous->mrr_current ?? 0)),
            'mrr_expected' => (float) ($withMrr ? 0 : ($previous->mrr_expected ?? 0)),
        ];

        if ($workspace->is_manually_billed) {
            // Billed outside Lemon Squeezy (via our own accounting system). MRR is computed
            // locally from usage at the workspace's unit price — no LS API call. Highest
            // precedence so it wins over any stale LS subscription.
            $billing['subscription_status'] = 'manual';
            $billing['billing_interval'] = 'monthly';
            $billing['mrr_current'] = $workspace->calculateManualMrr($displays, $boards);
            $billing['mrr_expected'] = $billing['mrr_current'];

            return $billing;
        }

        if ($workspace->is_unlimited) {
            $billing['subscription_status'] = 'unlimited';

            return $billing;
        }

        $subscription = $workspace->subscriptions->first();

        if (! $subscription) {
            return $billing;
        }

        $billing['lemon_squeezy_id'] = $subscription->lemon_squeezy_id;
        $billing['trial_ends_at'] = $subscription->trial_ends_at;
        $billing['subscription_ends_at'] = $subscription->ends_at;
        $billing['subscription_renews_at'] = $subscription->renews_at;
        $billing['subscription_status'] = $subscription->status ?? 'unknown';

        if ($withMrr) {
            $apiData = $this->fetchSubscriptionFromApi(
                $subscription->lemon_squeezy_id,
                Workspace::calculateUsage($displays, $boards)
            );

            if ($apiData) {
                $billing['subscription_status'] = $apiData['status'];
                $billing['billing_interval'] = $apiData['billing_interval'];
                // unit_price × quantity already factored in fetchSubscriptionPrice
                $billing['mrr_current'] = $apiData['status'] === 'active' ? $apiData['mrr'] : 0;
                $billing['mrr_expected'] = in_array($apiData['status'], ['active', 'on_trial']) ? $apiData['mrr'] : 0;
            }
        }

        return $billing;
    }

    /**
     * A change in what this workspace is charged for, measured against the last snapshot.
     *
     * Billable usage is the trigger; the MRR either side is recorded alongside it. A
     * workspace without a previous snapshot is skipped, so the first run after a deploy does
     * not report every existing customer as having grown from zero.
     *
     * @param  array<string, mixed>  $billing
     * @return array<string, mixed>|null
     */
    private function detectLicenceChange(Workspace $workspace, array $billing, ?User $billingUser, ?object $previous, Carbon $now): ?array
    {
        if (! $previous || ! $billingUser) {
            return null;
        }

        $newLicenceCount = Workspace::calculateUsage($billing['displays_count'], $billing['boards_count']);
        $previousLicenceCount = Workspace::calculateUsage((int) $previous->displays_count, (int) $previous->boards_count);

        if ($newLicenceCount === $previousLicenceCount) {
            return null;
        }

        $previousMrr = (float) $previous->mrr_current;

        return [
            // Who to contact: the member carrying the billing at the moment of detection.
            'user_id' => $billingUser->id,
            'email' => $billingUser->email,
            'name' => $billingUser->name,
            // What actually changed.
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'previous_displays_count' => (int) $previous->displays_count,
            'new_displays_count' => $billing['displays_count'],
            'previous_boards_count' => (int) $previous->boards_count,
            'new_boards_count' => $billing['boards_count'],
            'previous_license_count' => $previousLicenceCount,
            'new_license_count' => $newLicenceCount,
            'license_delta' => $newLicenceCount - $previousLicenceCount,
            'previous_mrr' => $previousMrr,
            'new_mrr' => $billing['mrr_current'],
            'mrr_delta' => $billing['mrr_current'] - $previousMrr,
            'change_type' => $newLicenceCount > $previousLicenceCount ? 'increase' : 'decrease',
            'subscription_status' => $billing['subscription_status'],
            'detected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * One row per membership.
     *
     * `$billing` is filled only for the member who carries the workspace's billing; every
     * other row keeps the workspace figures at zero, so a SUM over this table is a sum over
     * workspaces rather than over colleagues.
     *
     * @param  array<string, mixed>|null  $billing
     * @return array<string, mixed>
     */
    private function userRow(User $user, ?Workspace $workspace, ?array $billing, Carbon $now): array
    {
        return [
            'user_id' => $user->id,
            'workspace_id' => $workspace?->id,
            'workspace_name' => $workspace?->name,
            'is_billing_owner' => $billing !== null,
            'email' => $user->email,
            'name' => $user->name,
            'registered_at' => $user->created_at,
            'last_user_activity_at' => $user->last_activity_at,
            // Workspace-wide, and on every member's row rather than only the billing owner's:
            // the tablets belong to the team, so a colleague in a workspace whose displays are
            // live is an active user too. Safe to repeat because it is a timestamp, not a
            // figure anything sums.
            'last_device_activity_at' => $workspace?->devices_max_last_activity_at,
            'is_unlimited' => $billing['is_unlimited'] ?? false,
            'displays_count' => $billing['displays_count'] ?? 0,
            'boards_count' => $billing['boards_count'] ?? 0,
            'rooms_count' => $billing['rooms_count'] ?? 0,
            // 'member' rather than 'none': this row is not an account that never subscribed,
            // it is a colleague on someone else's plan. Lumping the two together would
            // inflate every count of unconverted accounts.
            'subscription_status' => $billing['subscription_status'] ?? ($workspace ? 'member' : 'none'),
            'billing_interval' => $billing['billing_interval'] ?? null,
            'trial_ends_at' => $billing['trial_ends_at'] ?? null,
            'subscription_ends_at' => $billing['subscription_ends_at'] ?? null,
            'subscription_renews_at' => $billing['subscription_renews_at'] ?? null,
            'lemon_squeezy_id' => $billing['lemon_squeezy_id'] ?? null,
            'mrr_current' => $billing['mrr_current'] ?? 0,
            'mrr_expected' => $billing['mrr_expected'] ?? 0,
            'refreshed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertUserRows(array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('analytics_users')->upsert(
                $chunk,
                ['user_id', 'workspace_id'],
                ['workspace_name', 'is_billing_owner', 'email', 'name', 'registered_at', 'last_user_activity_at',
                    'last_device_activity_at', 'is_unlimited', 'displays_count', 'boards_count', 'rooms_count',
                    'subscription_status', 'billing_interval', 'trial_ends_at', 'subscription_ends_at',
                    'subscription_renews_at', 'lemon_squeezy_id', 'mrr_current', 'mrr_expected', 'refreshed_at', 'updated_at']
            );
        }
    }

    private function refreshInstances(bool $withMrr): void
    {
        $now = now();

        $instances = Instance::all();

        $this->line("Processing {$instances->count()} instances...");

        // Preserve existing MRR values on fast runs
        $existingMrr = $withMrr ? [] : DB::table('analytics_instances')->pluck('mrr_current', 'instance_id')->all();
        $existingMrrExpected = $withMrr ? [] : DB::table('analytics_instances')->pluck('mrr_expected', 'instance_id')->all();
        $existingInterval = $withMrr ? [] : DB::table('analytics_instances')->pluck('billing_interval', 'instance_id')->all();
        $existingLsId = $withMrr ? [] : DB::table('analytics_instances')->pluck('lemon_squeezy_id', 'instance_id')->all();

        $rows = [];
        foreach ($instances as $instance) {
            $subscriptionStatus = $instance->license_valid ? 'active' : 'inactive';
            $billingInterval = $existingInterval[$instance->id] ?? null;
            $lemonSqueezyId = $existingLsId[$instance->id] ?? null;
            $mrrCurrent = (float) ($existingMrr[$instance->id] ?? 0);
            $mrrExpected = (float) ($existingMrrExpected[$instance->id] ?? 0);

            if ($withMrr && $instance->license_key) {
                // Billable usage: displays count as 1x, boards as 2x (see Workspace::getTotalUsageCount)
                $billableUsage = max(1, (int) $instance->displays_count + ((int) $instance->boards_count * 2));
                $instanceMrr = $this->fetchInstanceMrr($instance->license_key, $billableUsage);

                if ($instanceMrr) {
                    $subscriptionStatus = $instanceMrr['status'];
                    $billingInterval = $instanceMrr['billing_interval'];
                    $lemonSqueezyId = $instanceMrr['subscription_id'];
                    $mrrCurrent = $subscriptionStatus === 'active' ? $instanceMrr['mrr'] : 0;
                    $mrrExpected = in_array($subscriptionStatus, ['active', 'on_trial']) ? $instanceMrr['mrr'] : 0;
                }
            }

            $rows[] = [
                'instance_id' => $instance->id,
                'lemon_squeezy_id' => $lemonSqueezyId,
                'instance_key' => $instance->instance_key,
                'version' => $instance->version,
                'displays_count' => $instance->displays_count,
                'rooms_count' => $instance->rooms_count,
                'boards_count' => $instance->boards_count,
                'is_paid' => (bool) $instance->license_valid,
                'subscription_status' => $subscriptionStatus,
                'billing_interval' => $billingInterval,
                'license_expires_at' => $instance->license_expires_at,
                'last_heartbeat_at' => $instance->last_heartbeat_at,
                'last_validated_at' => $instance->last_validated_at,
                'registered_at' => $instance->created_at,
                'mrr_current' => $mrrCurrent,
                'mrr_expected' => $mrrExpected,
                'refreshed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('analytics_instances')->upsert(
                $chunk,
                ['instance_id'],
                ['lemon_squeezy_id', 'instance_key', 'version', 'displays_count', 'rooms_count', 'boards_count',
                    'is_paid', 'subscription_status', 'billing_interval', 'license_expires_at', 'last_heartbeat_at',
                    'last_validated_at', 'registered_at', 'mrr_current', 'mrr_expected', 'refreshed_at', 'updated_at']
            );
        }

        // Only prune when at least one instance was seen. With an empty id list, whereNotIn
        // matches every row and would wipe the whole snapshot table.
        $instanceIds = $instances->pluck('id')->all();
        if ($instanceIds !== []) {
            DB::table('analytics_instances')->whereNotIn('instance_id', $instanceIds)->delete();
        }

        $this->line("Upserted {$instances->count()} instance rows.");
    }

    private function fetchInstanceMrr(string $licenseKey, int $billableUsage): ?array
    {
        $apiKey = config('lemon-squeezy.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            // Step 1: find the license key record in LS to get the customer_id
            $licenseData = $this->cachedGet(
                "lemonsqueezy:license-key-lookup:{$licenseKey}",
                'https://api.lemonsqueezy.com/v1/license-keys',
                ['filter[key]' => $licenseKey],
                ttlHours: 6,
                isUsable: fn (array $body) => isset($body['data'][0]['attributes']['customer_id']),
            );

            $customerId = $licenseData['data'][0]['attributes']['customer_id'] ?? null;
            if (! $customerId) {
                return null;
            }

            // Step 2: find subscriptions for this customer
            $subsData = $this->cachedGet(
                "lemonsqueezy:customer-subscriptions:{$customerId}",
                'https://api.lemonsqueezy.com/v1/subscriptions',
                ['filter[customer_id]' => $customerId],
                ttlHours: 6,
                isUsable: fn (array $body) => ! empty($body['data']),
            );

            if (! $subsData) {
                return null;
            }

            // Prefer active, fall back to the most recent
            $sub = collect($subsData['data'])
                ->sortByDesc(fn ($s) => $s['attributes']['created_at'])
                ->first(fn ($s) => in_array($s['attributes']['status'], ['active', 'on_trial']))
                ?? collect($subsData['data'])->sortByDesc(fn ($s) => $s['attributes']['created_at'])->first();

            if (! $sub) {
                return null;
            }

            $subscriptionId = $sub['id'];
            $apiData = $this->fetchSubscriptionFromApi($subscriptionId, $billableUsage);

            if (! $apiData) {
                return null;
            }

            return array_merge($apiData, ['subscription_id' => $subscriptionId]);
        } catch (\Exception $e) {
            logger()->warning('RefreshAnalytics: failed to fetch instance MRR from LemonSqueezy', [
                'license_key' => substr($licenseKey, 0, 8).'…',
                'billable_usage' => $billableUsage,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchSubscriptionFromApi(string $subscriptionId, int $quantity): ?array
    {
        $apiKey = config('lemon-squeezy.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $subscriptionData = $this->cachedGet(
                "lemonsqueezy:subscription:{$subscriptionId}",
                "https://api.lemonsqueezy.com/v1/subscriptions/{$subscriptionId}",
                [],
                ttlHours: 6,
                isUsable: fn (array $body) => isset($body['data']['attributes']),
            );

            if (! $subscriptionData) {
                return null;
            }

            $status = $subscriptionData['data']['attributes']['status'] ?? null;
            $unitPrice = $this->fetchUnitPrice($subscriptionId);
            $interval = $this->resolveInterval($subscriptionId);

            if ($unitPrice === null) {
                return null;
            }

            // MRR = unit price × quantity (billable usage: displays 1x + boards 2x)
            $mrr = $unitPrice * max(1, $quantity);

            return [
                'status' => $status,
                'mrr' => $mrr,
                'billing_interval' => $interval,
            ];
        } catch (\Exception $e) {
            logger()->warning('RefreshAnalytics: failed to fetch subscription from LemonSqueezy', [
                'subscription_id' => $subscriptionId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A cached GET against Lemon Squeezy that only ever stores an answer it can use.
     *
     * Cache::remember() kept whatever came back, so a single 404 or rate-limited reply froze
     * the figures for the whole TTL: every later run read the error straight from cache, made
     * no request at all, and finished in a second having updated nothing. Six hours of that
     * for a subscription, a full day for a price — with no sign anything was wrong.
     *
     * @param  array<string, mixed>  $query
     * @param  callable(array<mixed>): bool  $isUsable
     * @return array<mixed>|null
     */
    private function cachedGet(string $cacheKey, string $url, array $query, int $ttlHours, callable $isUsable): ?array
    {
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $isUsable($cached)) {
            return $cached;
        }

        $body = Http::withToken(config('lemon-squeezy.api_key'))
            ->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->timeout(15)
            ->get($url, $query)
            ->json();

        if (! is_array($body) || ! $isUsable($body)) {
            logger()->warning('RefreshAnalytics: unusable response from LemonSqueezy, left uncached so the next run retries', [
                'url' => $url,
                'query' => $query,
                'error' => $body['errors'][0]['title'] ?? null,
            ]);

            return null;
        }

        Cache::put($cacheKey, $body, now()->addHours($ttlHours));

        return $body;
    }

    private function fetchUnitPrice(string $subscriptionId): ?float
    {
        $itemsData = $this->cachedGet(
            "lemonsqueezy:subscription-items:{$subscriptionId}",
            'https://api.lemonsqueezy.com/v1/subscription-items',
            ['filter[subscription_id]' => $subscriptionId],
            ttlHours: 6,
            isUsable: fn (array $body) => isset($body['data'][0]['attributes']['price_id']),
        );

        if (! $itemsData) {
            return null;
        }

        $priceId = $itemsData['data'][0]['attributes']['price_id'];

        $priceData = $this->cachedGet(
            "lemonsqueezy:price:{$priceId}",
            "https://api.lemonsqueezy.com/v1/prices/{$priceId}",
            [],
            ttlHours: 24,
            isUsable: fn (array $body) => isset($body['data']['attributes']),
        );

        if (! $priceData) {
            return null;
        }

        $attrs = $priceData['data']['attributes'];
        $raw = $attrs['unit_price_decimal'] ?? $attrs['unit_price'] ?? null;

        if ($raw === null) {
            return null;
        }

        $unitPrice = (float) ($raw / 100);
        $isYearly = strtolower($attrs['renewal_interval_unit'] ?? '') === 'year';

        // Convert yearly unit price to monthly equivalent
        return $isYearly ? $unitPrice / 12 : $unitPrice;
    }

    private function resolveInterval(string $subscriptionId): ?string
    {
        $itemsData = Cache::get("lemonsqueezy:subscription-items:{$subscriptionId}");
        if (empty($itemsData['data'])) {
            return null;
        }

        $priceId = $itemsData['data'][0]['attributes']['price_id'] ?? null;
        if (! $priceId) {
            return null;
        }

        $priceData = Cache::get("lemonsqueezy:price:{$priceId}");
        $interval = strtolower($priceData['data']['attributes']['renewal_interval_unit'] ?? '');

        return match ($interval) {
            'year' => 'yearly',
            'month' => 'monthly',
            default => null,
        };
    }
}
