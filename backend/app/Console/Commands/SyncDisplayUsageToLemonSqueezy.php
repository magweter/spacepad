<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDisplayUsageToLemonSqueezy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-display-usage-to-lemonsqueezy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync display usage to Lemon Squeezy for users with active subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Skipping display usage sync - this is a self-hosted instance');
            return self::SUCCESS;
        }

        $this->info('Starting display usage sync to Lemon Squeezy...');

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
                
                if ($displayCount > 0) {
                    if ($user->is_unlimited) {
                        $this->line("Skipping unlimited user {$user->email} with {$displayCount} displays");
                        $successCount++;
                    } else {
                        $this->reportUsageToLemonSqueezy($user, $displayCount);
                        $successCount++;
                        $this->info("Synced {$displayCount} displays for user {$user->email}");
                    }
                } else {
                    $this->line("No active displays for user {$user->email}");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Failed to sync usage for user {$user->email}: {$e->getMessage()}");
                Log::error('Display usage sync failed', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Sync completed: {$successCount} successful, {$errorCount} errors");
        
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
     * Report usage to Lemon Squeezy API
     */
    private function reportUsageToLemonSqueezy(User $user, int $displayCount): void
    {
        // Skip unlimited users as they don't need usage reporting
        if ($user->is_unlimited) {
            return;
        }

        // Get the user's active subscription
        $subscription = $user->subscriptions()
            ->where('ends_at', null)
            ->orWhere('ends_at', '>', now())
            ->first();

        if (!$subscription) {
            throw new \Exception('No active subscription found for user');
        }

        $apiKey = config('lemon-squeezy.api_key');
        if (!$apiKey) {
            throw new \Exception('Lemon Squeezy API key not configured');
        }

        // First, get the subscription details from Lemon Squeezy API to find subscription items
        $subscriptionResponse = Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
            ])
            ->get('https://api.lemonsqueezy.com/v1/subscriptions/' . $subscription->lemon_squeezy_id);

        if (!$subscriptionResponse->successful()) {
            throw new \Exception('Failed to fetch subscription from Lemon Squeezy: ' . $subscriptionResponse->body());
        }

        $subscriptionData = $subscriptionResponse->json();
        
        // Log the response structure for debugging
        Log::info('Lemon Squeezy subscription response', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->lemon_squeezy_id,
            'response_structure' => array_keys($subscriptionData['data'] ?? []),
            'attributes_keys' => array_keys($subscriptionData['data']['attributes'] ?? [])
        ]);
        
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
            Log::info('Attempting to fetch subscription items directly', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->lemon_squeezy_id
            ]);
            
            $subscriptionItemsResponse = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/vnd.api+json',
                ])
                ->get('https://api.lemonsqueezy.com/v1/subscription-items?filter[subscription_id]=' . $subscription->lemon_squeezy_id);

            if (!$subscriptionItemsResponse->successful()) {
                throw new \Exception('Failed to fetch subscription items from Lemon Squeezy: ' . $subscriptionItemsResponse->body());
            }

            $subscriptionItemsData = $subscriptionItemsResponse->json();
            
            if (!isset($subscriptionItemsData['data']) || empty($subscriptionItemsData['data'])) {
                throw new \Exception('No subscription items found for subscription. Response: ' . json_encode($subscriptionItemsData));
            }
            
            $subscriptionItems = $subscriptionItemsData['data'];
        }

        if (empty($subscriptionItems)) {
            throw new \Exception('No subscription items found for metered usage');
        }

        // Find the first subscription item (assuming single item per subscription)
        $subscriptionItem = $subscriptionItems[0];
        
        // Handle different response structures
        if (isset($subscriptionItem['id'])) {
            // Direct ID in the item
            $subscriptionItemId = $subscriptionItem['id'];
        } elseif (isset($subscriptionItem['attributes']['id'])) {
            // ID in attributes
            $subscriptionItemId = $subscriptionItem['attributes']['id'];
        } else {
            Log::error('Unexpected subscription item structure', [
                'user_id' => $user->id,
                'subscription_item' => $subscriptionItem
            ]);
            throw new \Exception('Subscription item ID not found in response structure: ' . json_encode($subscriptionItem));
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

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Lemon Squeezy API error response', [
                'status' => $response->status(),
                'body' => $errorBody,
                'user_id' => $user->id,
                'subscription_item_id' => $subscriptionItemId
            ]);
            throw new \Exception('Failed to report usage to Lemon Squeezy: ' . $errorBody);
        }

        Log::info('Display usage reported to Lemon Squeezy', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'subscription_id' => $subscription->lemon_squeezy_id,
            'subscription_item_id' => $subscriptionItemId,
            'display_count' => $displayCount,
            'response_status' => $response->status(),
            'response_body' => $response->json()
        ]);
    }
}
