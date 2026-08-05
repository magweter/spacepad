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

    protected $description = 'Refresh the analytics_workspaces and analytics_instances snapshot tables for Metabase';

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

            $this->refreshWorkspaces($withMrr);
            $this->refreshInstances($withMrr);

            $this->info('Analytics refresh completed.');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function refreshWorkspaces(bool $withMrr): void
    {
        $now = now();

        // Last run's money figures, so a fast refresh does not clobber what an --mrr run
        // fetched from the API. Usage is not carried over: it is read straight off the
        // workspace's counter columns, which are always current.
        $previous = DB::table('analytics_workspaces')
            ->select('workspace_id', 'mrr_current', 'mrr_expected', 'billing_interval')
            ->get()
            ->keyBy('workspace_id');

        $rowCount = 0;

        // One row per workspace, because that is what carries a subscription. Chunked so
        // memory stays flat as the table grows.
        Workspace::query()
            ->withCount(['rooms', 'members'])
            ->withMax('devices', 'last_activity_at')
            ->withMax('members', 'last_activity_at')
            ->with([
                'members' => fn ($q) => $q->whereNull('users.deleted_at')->orderByPivot('created_at'),
                'billingOwnerUser',
                'subscriptions' => fn ($q) => $q
                    ->where(fn ($sub) => $sub->whereNull('ends_at')->orWhere('ends_at', '>', $now))
                    ->orderByDesc('created_at'),
            ])
            ->chunkById(200, function ($workspaces) use (&$rowCount, $withMrr, $now, $previous) {
                $rows = [];

                foreach ($workspaces as $workspace) {
                    $billing = $this->resolveBilling($workspace, $withMrr, $previous->get($workspace->id));
                    $rows[] = $this->workspaceRow($workspace, $billing, $now);

                    if ($withMrr) {
                        $this->priceOutstandingChanges($workspace, $billing);
                    }
                }

                $rowCount += count($rows);
                $this->upsertWorkspaceRows($rows);
            });

        // Whatever this run did not stamp belongs to a workspace that is gone. Only prune
        // when something was seen: after a failed run the timestamp filter would otherwise
        // match every row and wipe the snapshot.
        if ($rowCount > 0) {
            DB::table('analytics_workspaces')->where('refreshed_at', '<', $now)->delete();
        }

        $this->line("Upserted {$rowCount} workspace rows.");
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
        // Straight off the counter columns. Nothing here counts rows: what the snapshot
        // reports and what the customer is invoiced for are the same two numbers.
        $displays = (int) $workspace->displays_count;
        $boards = (int) $workspace->boards_count;

        $billing = [
            'displays_count' => $displays,
            'boards_count' => $boards,
            'units' => Workspace::calculateUsage($displays, $boards),
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
            $billing['mrr_current'] = $workspace->calculateManualMrr();
            $billing['mrr_expected'] = $billing['mrr_current'];

            return $billing;
        }

        if ($workspace->is_unlimited) {
            $billing['subscription_status'] = 'unlimited';

            return $billing;
        }

        $subscription = $workspace->liveSubscription();

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
                $billing['units']
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
     * One workspace, as the reporting layer sees it.
     *
     * @param  array<string, mixed>  $billing
     * @return array<string, mixed>
     */
    private function workspaceRow(Workspace $workspace, array $billing, Carbon $now): array
    {
        $contact = $this->resolveBillingUser($workspace);

        return [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'members_count' => (int) ($workspace->members_count ?? 0),
            'billing_user_id' => $contact?->id,
            'billing_user_email' => $contact?->email,
            'billing_user_name' => $contact?->name,
            'displays_count' => $billing['displays_count'],
            'boards_count' => $billing['boards_count'],
            'rooms_count' => $billing['rooms_count'],
            'units' => $billing['units'],
            'is_unlimited' => $billing['is_unlimited'],
            'is_manually_billed' => (bool) $workspace->is_manually_billed,
            'manual_billing_unit_price' => $workspace->manual_billing_unit_price,
            'subscription_status' => $billing['subscription_status'],
            'billing_interval' => $billing['billing_interval'],
            'trial_ends_at' => $billing['trial_ends_at'],
            'subscription_ends_at' => $billing['subscription_ends_at'],
            'subscription_renews_at' => $billing['subscription_renews_at'],
            'lemon_squeezy_id' => $billing['lemon_squeezy_id'],
            'mrr_current' => $billing['mrr_current'],
            'mrr_expected' => $billing['mrr_expected'],
            'workspace_created_at' => $workspace->created_at,
            'last_member_activity_at' => $workspace->members_max_last_activity_at,
            'last_device_activity_at' => $workspace->devices_max_last_activity_at,
            'refreshed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertWorkspaceRows(array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('analytics_workspaces')->upsert(
                $chunk,
                ['workspace_id'],
                ['workspace_name', 'members_count', 'billing_user_id', 'billing_user_email', 'billing_user_name',
                    'displays_count', 'boards_count', 'rooms_count', 'units', 'is_unlimited', 'is_manually_billed',
                    'manual_billing_unit_price', 'subscription_status', 'billing_interval', 'trial_ends_at',
                    'subscription_ends_at', 'subscription_renews_at', 'lemon_squeezy_id', 'mrr_current',
                    'mrr_expected', 'workspace_created_at', 'last_member_activity_at', 'last_device_activity_at',
                    'refreshed_at', 'updated_at']
            );
        }
    }

    /**
     * Put a price on the changes RecordBillingChange could not price itself.
     *
     * A usage change is recorded the moment it happens, which is long before anyone has
     * asked Lemon Squeezy what the subscription is now worth. Those rows carry a null
     * new_mrr until a run with --mrr gets an answer.
     *
     * Priced per row from the unit rate rather than by stamping the current MRR on all of
     * them: several changes can pile up between two runs, and giving each of them the
     * end-state figure would say the first display cost as much as all of them together.
     *
     * @param  array<string, mixed>  $billing
     */
    private function priceOutstandingChanges(Workspace $workspace, array $billing): void
    {
        $units = (int) $billing['units'];
        $mrr = (float) $billing['mrr_current'];

        if ($units < 1 || $mrr <= 0) {
            return;
        }

        $unitPrice = $mrr / $units;

        // Fetched up front rather than chunked. Writing new_mrr takes each row out of the
        // whereNull filter, so a paged walk would shift under itself and skip rows. The set
        // is only what has gone unpriced since the last --mrr run, per workspace.
        $pending = DB::table('billing_changes')
            ->where('workspace_id', $workspace->id)
            ->whereNull('new_mrr')
            ->orderBy('id')
            ->get();

        foreach ($pending as $change) {
            $newMrr = round($unitPrice * (int) $change->new_license_count, 2);
            $previousMrr = $change->previous_mrr !== null
                ? (float) $change->previous_mrr
                : round($unitPrice * (int) $change->previous_license_count, 2);

            DB::table('billing_changes')->where('id', $change->id)->update([
                'previous_mrr' => $previousMrr,
                'new_mrr' => $newMrr,
                'mrr_delta' => round($newMrr - $previousMrr, 2),
                'updated_at' => now(),
            ]);
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
                // A self-hosted instance reports its totals over the wire rather than
                // holding workspaces, but it is priced by the same rule.
                $billableUsage = max(1, Workspace::calculateUsage(
                    (int) $instance->displays_count,
                    (int) $instance->boards_count,
                ));
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
