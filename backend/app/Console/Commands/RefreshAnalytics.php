<?php

namespace App\Console\Commands;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Console\Command;
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

        // Preserve existing MRR values on fast (non-MRR) runs
        $existingMrr = $withMrr ? [] : DB::table('analytics_users')->pluck('mrr_current', 'user_id')->all();
        $existingMrrExpected = $withMrr ? [] : DB::table('analytics_users')->pluck('mrr_expected', 'user_id')->all();
        $existingInterval = $withMrr ? [] : DB::table('analytics_users')->pluck('billing_interval', 'user_id')->all();

        // Previous snapshot for license-count / MRR change detection (always needed)
        $previous = DB::table('analytics_users')
            ->select('user_id', 'displays_count', 'boards_count', 'mrr_current')
            ->get()
            ->keyBy('user_id');

        $changes = [];
        $userIds = [];

        // Process users in batches (with the same eager-loaded relations/counts) so memory
        // stays flat as the users table grows instead of loading every user at once.
        User::withCount(['displays', 'boards', 'rooms'])
            ->with([
                'devices' => fn ($q) => $q->whereNotNull('last_activity_at')->orderByDesc('last_activity_at')->limit(1),
                'subscriptions' => fn ($q) => $q->where(fn ($s) => $s->whereNull('ends_at')->orWhere('ends_at', '>', $now))->orderByDesc('created_at'),
                'workspaces' => fn ($q) => $q->withPivot('role')->orderByPivot('created_at'),
            ])
            ->whereNull('deleted_at')
            ->chunkById(500, function ($users) use (&$changes, &$userIds, $withMrr, $now, $existingMrr, $existingMrrExpected, $existingInterval, $previous) {
                $rows = [];

                foreach ($users as $user) {
                    $subscription = $user->subscriptions->first();
                    $subscriptionStatus = 'none';
                    $billingInterval = $existingInterval[$user->id] ?? null;
                    $lemonSqueezyId = null;
                    $trialEndsAt = null;
                    $subscriptionEndsAt = null;
                    $subscriptionRenewsAt = null;
                    $mrrCurrent = (float) ($existingMrr[$user->id] ?? 0);
                    $mrrExpected = (float) ($existingMrrExpected[$user->id] ?? 0);

                    if ($user->is_manually_billed) {
                        // Billed outside Lemon Squeezy (via our own accounting system).
                        // MRR is computed locally from usage at the account's unit price
                        // (falling back to the global default) — no LS API call.
                        // Highest precedence so it wins over any stale LS subscription.
                        $subscriptionStatus = 'manual';
                        $billingInterval = 'monthly';
                        $mrrCurrent = $user->calculateManualMrr($user->displays_count, $user->boards_count);
                        $mrrExpected = $mrrCurrent;
                    } elseif ($user->is_unlimited) {
                        $subscriptionStatus = 'unlimited';
                    } elseif ($subscription) {
                        $lemonSqueezyId = $subscription->lemon_squeezy_id;
                        $trialEndsAt = $subscription->trial_ends_at;
                        $subscriptionEndsAt = $subscription->ends_at;
                        $subscriptionRenewsAt = $subscription->renews_at;
                        $subscriptionStatus = $subscription->status ?? 'unknown';

                        if ($withMrr) {
                            // Billable usage: displays count as 1x, boards as 2x (see Workspace::getTotalUsageCount)
                            $billableUsage = $user->displays_count + ($user->boards_count * 2);
                            $apiData = $this->fetchSubscriptionFromApi($subscription->lemon_squeezy_id, $billableUsage);

                            if ($apiData) {
                                $subscriptionStatus = $apiData['status'];
                                $billingInterval = $apiData['billing_interval'];
                                // unit_price × quantity already factored in fetchSubscriptionPrice
                                $mrrCurrent = $subscriptionStatus === 'active' ? $apiData['mrr'] : 0;
                                $mrrExpected = in_array($subscriptionStatus, ['active', 'on_trial']) ? $apiData['mrr'] : 0;
                            }
                        }
                    }

                    $primaryWorkspace = $user->workspaces->first(fn ($w) => $w->pivot->role === \App\Enums\WorkspaceRole::OWNER->value)
                        ?? $user->workspaces->first();

                    // Detect a license-count change vs. the previous snapshot. License count
                    // (billable usage) is the trigger; MRR old/new is recorded alongside it.
                    // Skip users with no previous snapshot (new users / first-ever run) to avoid
                    // a spurious "0 → N" increase for every existing user.
                    $prevRow = $previous->get($user->id);
                    if ($prevRow) {
                        $newLicenseCount = $user->displays_count + ($user->boards_count * 2);
                        $prevLicenseCount = (int) $prevRow->displays_count + ((int) $prevRow->boards_count * 2);

                        if ($newLicenseCount !== $prevLicenseCount) {
                            $prevMrr = (float) $prevRow->mrr_current;

                            $changes[] = [
                                'user_id' => $user->id,
                                'email' => $user->email,
                                'name' => $user->name,
                                'previous_displays_count' => (int) $prevRow->displays_count,
                                'new_displays_count' => $user->displays_count,
                                'previous_boards_count' => (int) $prevRow->boards_count,
                                'new_boards_count' => $user->boards_count,
                                'previous_license_count' => $prevLicenseCount,
                                'new_license_count' => $newLicenseCount,
                                'license_delta' => $newLicenseCount - $prevLicenseCount,
                                'previous_mrr' => $prevMrr,
                                'new_mrr' => $mrrCurrent,
                                'mrr_delta' => $mrrCurrent - $prevMrr,
                                'change_type' => $newLicenseCount > $prevLicenseCount ? 'increase' : 'decrease',
                                'subscription_status' => $subscriptionStatus,
                                'detected_at' => $now,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    $rows[] = [
                        'user_id' => $user->id,
                        'workspace_id' => $primaryWorkspace?->id,
                        'workspace_name' => $primaryWorkspace?->name,
                        'email' => $user->email,
                        'name' => $user->name,
                        'registered_at' => $user->created_at,
                        'last_user_activity_at' => $user->last_activity_at,
                        'last_device_activity_at' => $user->devices->first()?->last_activity_at,
                        'is_unlimited' => $user->is_unlimited ?? false,
                        'displays_count' => $user->displays_count,
                        'boards_count' => $user->boards_count,
                        'rooms_count' => $user->rooms_count,
                        'subscription_status' => $subscriptionStatus,
                        'billing_interval' => $billingInterval,
                        'trial_ends_at' => $trialEndsAt,
                        'subscription_ends_at' => $subscriptionEndsAt,
                        'subscription_renews_at' => $subscriptionRenewsAt,
                        'lemon_squeezy_id' => $lemonSqueezyId,
                        'mrr_current' => $mrrCurrent,
                        'mrr_expected' => $mrrExpected,
                        'refreshed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $userIds[] = $user->id;
                }

                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('analytics_users')->upsert(
                        $chunk,
                        ['user_id'],
                        ['workspace_id', 'workspace_name', 'email', 'name', 'registered_at', 'last_user_activity_at',
                            'last_device_activity_at', 'is_unlimited', 'displays_count', 'boards_count', 'rooms_count',
                            'subscription_status', 'billing_interval', 'trial_ends_at', 'subscription_ends_at',
                            'subscription_renews_at', 'lemon_squeezy_id', 'mrr_current', 'mrr_expected', 'refreshed_at', 'updated_at']
                    );
                }
            });

        // Only prune when at least one user was seen. With an empty id list, whereNotIn
        // matches every row and would wipe the whole snapshot table.
        if ($userIds !== []) {
            DB::table('analytics_users')->whereNotIn('user_id', $userIds)->delete();
        }

        $this->line('Upserted '.count($userIds).' user rows.');

        foreach (array_chunk($changes, 100) as $chunk) {
            DB::table('billing_changes')->insert($chunk);
        }

        if ($changes !== []) {
            $this->line('Recorded '.count($changes).' billing change(s).');
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
            $licenseData = Cache::remember(
                "lemonsqueezy:license-key-lookup:{$licenseKey}",
                now()->addHours(6),
                fn () => Http::withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->timeout(15)
                    ->get('https://api.lemonsqueezy.com/v1/license-keys', ['filter[key]' => $licenseKey])
                    ->json()
            );

            $customerId = $licenseData['data'][0]['attributes']['customer_id'] ?? null;
            if (! $customerId) {
                return null;
            }

            // Step 2: find subscriptions for this customer
            $subsData = Cache::remember(
                "lemonsqueezy:customer-subscriptions:{$customerId}",
                now()->addHours(6),
                fn () => Http::withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->timeout(15)
                    ->get('https://api.lemonsqueezy.com/v1/subscriptions', ['filter[customer_id]' => $customerId])
                    ->json()
            );

            if (empty($subsData['data'])) {
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
            $subscriptionData = Cache::remember(
                "lemonsqueezy:subscription:{$subscriptionId}",
                now()->addHours(6),
                fn () => Http::withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->timeout(15)
                    ->get("https://api.lemonsqueezy.com/v1/subscriptions/{$subscriptionId}")
                    ->json()
            );

            if (! isset($subscriptionData['data']['attributes'])) {
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

    private function fetchUnitPrice(string $subscriptionId): ?float
    {
        $apiKey = config('lemon-squeezy.api_key');

        $itemsData = Cache::remember(
            "lemonsqueezy:subscription-items:{$subscriptionId}",
            now()->addHours(6),
            fn () => Http::withToken($apiKey)
                ->withHeaders(['Accept' => 'application/vnd.api+json'])
                ->timeout(15)
                ->get("https://api.lemonsqueezy.com/v1/subscription-items?filter[subscription_id]={$subscriptionId}")
                ->json()
        );

        if (empty($itemsData['data'])) {
            return null;
        }

        $priceId = $itemsData['data'][0]['attributes']['price_id'] ?? null;
        if (! $priceId) {
            return null;
        }

        $priceData = Cache::remember(
            "lemonsqueezy:price:{$priceId}",
            now()->addHours(24),
            fn () => Http::withToken($apiKey)
                ->withHeaders(['Accept' => 'application/vnd.api+json'])
                ->timeout(15)
                ->get("https://api.lemonsqueezy.com/v1/prices/{$priceId}")
                ->json()
        );

        if (! isset($priceData['data']['attributes'])) {
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
