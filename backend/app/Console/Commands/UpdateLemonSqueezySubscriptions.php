<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateLemonSqueezySubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-lemonsqueezy-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Lemon Squeezy subscriptions using both quantity-based and usage-based billing methods';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Skipping subscription update - this is a self-hosted instance');
            return self::SUCCESS;
        }

        $this->info('Starting Lemon Squeezy subscription updates...');

        // Get all users with active subscriptions
        $usersWithSubscriptions = User::where(function ($query) {
            $query->where('is_unlimited', true)
                  ->orWhereHas('subscriptions', function ($subQuery) {
                      $subQuery->where('ends_at', null) // Active subscription
                               ->orWhere('ends_at', '>', now()); // Not expired
                  });
        })->get();

        $this->info("Found {$usersWithSubscriptions->count()} users with active subscriptions");

        $successCount = 0;
        $errorCount = 0;

        foreach ($usersWithSubscriptions as $user) {
            try {
                $displayCount = $this->getActiveDisplayCount($user);
                
                if ($user->is_unlimited) {
                    $this->line("Skipping unlimited user {$user->email} with {$displayCount} displays");
                    $successCount++;
                } else {
                    // Try both quantity-based and usage-based billing methods
                    $this->updateQuantityBasedBilling($user, $displayCount);
                    $this->updateUsageBasedBilling($user, $displayCount);
                    $successCount++;
                    $this->info("Updated subscription for user {$user->email} with {$displayCount} displays");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Failed to update subscription for user {$user->email}: {$e->getMessage()}");
                Log::error('Subscription update failed', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Subscription updates completed: {$successCount} successful, {$errorCount} errors");
        
        return $errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get the count of active displays for a user
     */
    private function getActiveDisplayCount(User $user): int
    {
        return $user->displays()
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->count();
    }

    /**
     * Update subscription using quantity-based billing
     */
    private function updateQuantityBasedBilling(User $user, int $displayCount): void
    {
        // Skip unlimited users as they don't need quantity updates
        if ($user->is_unlimited) {
            return;
        }

        // Get the user's active subscription
        $subscription = $user->subscriptions()
            ->where(function($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
            })
            ->first();

        if (!$subscription) {
            return; // No subscription found, skip silently
        }

        $apiKey = config('lemon-squeezy.api_key');
        if (!$apiKey) {
            return; // No API key, skip silently
        }

        try {
            // Get subscription details from Lemon Squeezy API to find subscription items
            $subscriptionResponse = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                ])
                ->get('https://api.lemonsqueezy.com/v1/subscriptions/' . $subscription->lemon_squeezy_id);

            if (!$subscriptionResponse->successful()) {
                return; // Failed to fetch subscription, skip silently
            }

            $subscriptionData = $subscriptionResponse->json();
            
            // Get subscription items from the response (handle different response structures)
            $subscriptionItems = $this->getSubscriptionItems($subscriptionData, $apiKey, $subscription->lemon_squeezy_id);

            if (empty($subscriptionItems)) {
                return; // No subscription items found, skip silently
            }

            // Find the first subscription item (assuming single item per subscription)
            $subscriptionItem = $subscriptionItems[0];
            $subscriptionItemId = $this->getSubscriptionItemId($subscriptionItem);

            if (!$subscriptionItemId) {
                return; // Could not get subscription item ID, skip silently
            }

            // Update subscription item quantity using quantity-based billing
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ])
                ->patch("https://api.lemonsqueezy.com/v1/subscription-items/{$subscriptionItemId}", [
                    'data' => [
                        'type' => 'subscription-items',
                        'id' => $subscriptionItemId,
                        'attributes' => [
                            'quantity' => $displayCount
                        ]
                    ]
                ]);

            if ($response->successful()) {
                Log::info('Quantity-based billing updated successfully', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'subscription_item_id' => $subscriptionItemId,
                    'display_count' => $displayCount
                ]);
            }

        } catch (\Exception $e) {
            // Log but don't throw - let the usage-based billing method try
            Log::debug('Quantity-based billing update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update subscription using usage-based billing
     */
    private function updateUsageBasedBilling(User $user, int $displayCount): void
    {
        // Skip unlimited users as they don't need usage reporting
        if ($user->is_unlimited) {
            return;
        }

        // Get the user's active subscription
        $subscription = $user->subscriptions()
            ->where(function($query) {
                $query->where('ends_at', null)
                      ->orWhere('ends_at', '>', now());
            })
            ->first();

        if (!$subscription) {
            return; // No subscription found, skip silently
        }

        $apiKey = config('lemon-squeezy.api_key');
        if (!$apiKey) {
            return; // No API key, skip silently
        }

        try {
            // Get subscription details from Lemon Squeezy API to find subscription items
            $subscriptionResponse = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                ])
                ->get('https://api.lemonsqueezy.com/v1/subscriptions/' . $subscription->lemon_squeezy_id);

            if (!$subscriptionResponse->successful()) {
                return; // Failed to fetch subscription, skip silently
            }

            $subscriptionData = $subscriptionResponse->json();
            
            // Get subscription items from the response (handle different response structures)
            $subscriptionItems = $this->getSubscriptionItems($subscriptionData, $apiKey, $subscription->lemon_squeezy_id);

            if (empty($subscriptionItems)) {
                return; // No subscription items found, skip silently
            }

            // Find the first subscription item (assuming single item per subscription)
            $subscriptionItem = $subscriptionItems[0];
            $subscriptionItemId = $this->getSubscriptionItemId($subscriptionItem);

            if (!$subscriptionItemId) {
                return; // Could not get subscription item ID, skip silently
            }

            // Report usage to Lemon Squeezy using the usage-records API endpoint
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ])
                ->post('https://api.lemonsqueezy.com/v1/usage-records', [
                    'data' => [
                        'type' => 'usage-records',
                        'attributes' => [
                            'quantity' => $displayCount,
                            'action' => 'set', // Set the usage count for the current period
                        ],
                        'relationships' => [
                            'subscription-item' => [
                                'data' => [
                                    'type' => 'subscription-items',
                                    'id' => $subscriptionItemId
                                ]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                Log::info('Usage-based billing updated successfully', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'subscription_item_id' => $subscriptionItemId,
                    'display_count' => $displayCount
                ]);
            }

        } catch (\Exception $e) {
            // Log but don't throw - let the quantity-based billing method try
            Log::debug('Usage-based billing update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract subscription items from Lemon Squeezy API response
     */
    private function getSubscriptionItems(array $subscriptionData, string $apiKey, string $subscriptionId): array
    {
        $subscriptionItems = [];
        
        // Check if subscription_items is in the attributes
        if (isset($subscriptionData['data']['attributes']['subscription_items'])) {
            $subscriptionItems = $subscriptionData['data']['attributes']['subscription_items'];
        }
        // Check if subscription_items is in the relationships
        elseif (isset($subscriptionData['data']['relationships']['subscription_items']['data'])) {
            $subscriptionItems = $subscriptionData['data']['relationships']['subscription_items']['data'];
        }
        // Check if subscription_items is in the included data
        elseif (isset($subscriptionData['included'])) {
            $subscriptionItems = collect($subscriptionData['included'])
                ->filter(fn($item) => $item['type'] === 'subscription-items')
                ->toArray();
        }
        else {
            // Try to fetch subscription items directly
            $subscriptionItemsResponse = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                ])
                ->get('https://api.lemonsqueezy.com/v1/subscription-items?filter[subscription_id]=' . $subscriptionId);

            if ($subscriptionItemsResponse->successful()) {
                $subscriptionItemsData = $subscriptionItemsResponse->json();
                
                if (isset($subscriptionItemsData['data']) && !empty($subscriptionItemsData['data'])) {
                    $subscriptionItems = $subscriptionItemsData['data'];
                }
            }
        }

        return $subscriptionItems;
    }

    /**
     * Extract subscription item ID from Lemon Squeezy API response
     */
    private function getSubscriptionItemId(array $subscriptionItem): ?string
    {
        // Handle different response structures
        if (isset($subscriptionItem['id'])) {
            return $subscriptionItem['id'];
        } elseif (isset($subscriptionItem['attributes']['id'])) {
            return $subscriptionItem['attributes']['id'];
        }

        return null;
    }
}
