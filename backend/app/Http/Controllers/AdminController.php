<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }

        $activeDisplays = Display::where('status', DisplayStatus::ACTIVE)->count();
        $totalDisplays = Display::count();
        $totalInstances = Instance::count();
        $sevenDaysAgo = now()->subDays(7);

        // Active self-hosted instances in the last 7 days, sorted by registration order
        $activeInstances = Instance::where('is_self_hosted', true)
            ->where('last_heartbeat_at', '>=', $sevenDaysAgo)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($instance) {
                $instance->is_paid = (bool) $instance->license_valid;
                return $instance;
            });

        // Active cloud-hosted displays: users with at least one display active in the last 7 days, sorted by registration order
        $activeDisplays = User::query()
            ->whereHas('displays', function($q) use ($sevenDaysAgo) {
                $q->where('last_sync_at', '>=', $sevenDaysAgo);
            })
            ->withCount(['displays' => function($q) use ($sevenDaysAgo) {
                $q->where('last_sync_at', '>=', $sevenDaysAgo);
            }])
            ->with(['displays' => function($q) use ($sevenDaysAgo) {
                $q->where('last_sync_at', '>=', $sevenDaysAgo)->orderByDesc('last_sync_at');
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($user) {
                $user->last_display_activity = $user->displays->max('last_sync_at');
                $user->is_paid = $user->hasPro();
                return $user;
            })
            ->values();

        // Paying cloud-hosted users: users with Pro subscription (is_unlimited or active subscription)
        $totalMRR = 0;
        $forecastedMRR = 0;
        $payingUsers = User::query()
            ->where(function($query) {
                $query->where('is_unlimited', true)
                    ->orWhereHas('subscriptions', function($subQuery) {
                        $subQuery->where(function($q) {
                            $q->whereNull('ends_at') // Active subscription
                              ->orWhere('ends_at', '>', now()); // Not expired
                        });
                    });
            })
            ->withCount('displays')
            ->with(['subscriptions' => function($query) {
                $query->where(function($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })->orderByDesc('created_at');
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($user) use (&$totalMRR, &$forecastedMRR) {
                $user->subscription_status = $user->is_unlimited 
                    ? 'Unlimited' 
                    : ($user->subscriptions->isNotEmpty() ? 'Subscribed' : 'None');
                $user->subscription_ends_at = $user->subscriptions->first()?->ends_at;
                
                // Fetch subscription price and status from Lemon Squeezy API
                $user->price = 0;
                $user->mrr = 0;
                $user->lemon_squeezy_status = null;
                
                if (!$user->is_unlimited && $user->subscriptions->isNotEmpty()) {
                    $subscription = $user->subscriptions->first();
                    $subscriptionData = $this->getSubscriptionData($subscription->lemon_squeezy_id, $user->displays_count);
                    
                    if ($subscriptionData) {
                        $user->lemon_squeezy_status = $subscriptionData['status'] ?? null;
                        $user->price = $subscriptionData['price'] ?? 0;
                        $user->mrr = $user->price * $user->displays_count;
                        
                        // Add to forecasted MRR (all statuses)
                        $forecastedMRR += $user->mrr;
                        
                        // Only add to total MRR if status is 'active'
                        if ($subscriptionData['status'] === 'active') {
                            $totalMRR += $user->mrr;
                        }
                    }
                }
                
                return $user;
            });

        return view('pages.admin', [
            'activeInstances' => $activeInstances,
            'activeDisplays' => $activeDisplays,
            'payingUsers' => $payingUsers,
            'activeDisplaysCount' => $activeDisplays->count(),
            'totalDisplays' => $totalDisplays,
            'activeInstancesCount' => $activeInstances->count(),
            'totalInstances' => $totalInstances,
            'payingUsersCount' => $payingUsers->count(),
            'totalMRR' => $totalMRR,
            'forecastedMRR' => $forecastedMRR,
        ]);
    }

    /**
     * Get subscription data (status, price, MRR) from Lemon Squeezy API
     * Returns array with 'status', 'price', and 'mrr' keys
     */
    private function getSubscriptionData(string $subscriptionId, int $displaysCount = 0): ?array
    {
        $apiKey = config('lemon-squeezy.api_key');
        if (!$apiKey) {
            return null;
        }

        try {
            // Cache key for subscription data
            $subscriptionCacheKey = "lemonsqueezy:subscription:{$subscriptionId}";
            
            // Fetch subscription to get status (cached for 1 hour)
            $subscriptionData = Cache::remember($subscriptionCacheKey, now()->addHour(), function () use ($apiKey, $subscriptionId) {
                $subscriptionResponse = Http::withToken($apiKey)
                    ->withHeaders([
                        'Accept' => 'application/vnd.api+json',
                    ])
                    ->get("https://api.lemonsqueezy.com/v1/subscriptions/{$subscriptionId}");

                if ($subscriptionResponse->successful()) {
                    return $subscriptionResponse->json();
                }
                
                return null;
            });

            if (!$subscriptionData || !isset($subscriptionData['data']['attributes'])) {
                return null;
            }

            $subscriptionAttributes = $subscriptionData['data']['attributes'];
            $status = $subscriptionAttributes['status'] ?? null;
            
            // Get price using existing method
            $price = $this->getSubscriptionPrice($subscriptionId, $displaysCount);
            
            if ($price === null) {
                return null;
            }

            // Calculate MRR (price is already calculated with quantity for usage-based)
            $mrr = $price;

            return [
                'status' => $status,
                'price' => $price,
                'mrr' => $mrr,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get subscription price from Lemon Squeezy API
     * Returns monthly recurring revenue (MRR) - converts yearly to monthly if needed
     * Handles both usage-based and non-usage-based subscriptions
     * For usage-based subscriptions, multiplies unit price by quantity (displays count)
     */
    private function getSubscriptionPrice(string $subscriptionId, int $displaysCount = 0): ?float
    {
        $apiKey = config('lemon-squeezy.api_key');
        if (!$apiKey) {
            return null;
        }

        try {
            // Cache key for subscription items
            $itemsCacheKey = "lemonsqueezy:subscription-items:{$subscriptionId}";
            
            // Fetch subscription items to get pricing (cached for 1 hour)
            $itemsData = Cache::remember($itemsCacheKey, now()->addHour(), function () use ($apiKey, $subscriptionId) {
                $itemsResponse = Http::withToken($apiKey)
                    ->withHeaders([
                        'Accept' => 'application/vnd.api+json',
                    ])
                    ->get("https://api.lemonsqueezy.com/v1/subscription-items?filter[subscription_id]={$subscriptionId}");

                if ($itemsResponse->successful()) {
                    return $itemsResponse->json();
                }
                
                return null;
            });

            if (!$itemsData || !isset($itemsData['data']) || empty($itemsData['data'])) {
                return null;
            }

            $item = $itemsData['data'][0];
            $itemAttributes = $item['attributes'] ?? [];
            $priceId = $itemAttributes['price_id'] ?? null;
            $isUsageBased = $itemAttributes['is_usage_based'] ?? false;
            $quantity = $itemAttributes['quantity'] ?? $displaysCount; // Use subscription item quantity or fallback to displays count
            
            if (!$priceId) {
                return null;
            }

            // Cache key for price details
            $priceCacheKey = "lemonsqueezy:price:{$priceId}";
            
            // Fetch price details using price_id (cached for 24 hours)
            $priceData = Cache::remember($priceCacheKey, now()->addHours(24), function () use ($apiKey, $priceId) {
                $priceResponse = Http::withToken($apiKey)
                    ->withHeaders([
                        'Accept' => 'application/vnd.api+json',
                    ])
                    ->get("https://api.lemonsqueezy.com/v1/prices/{$priceId}");

                if ($priceResponse->successful()) {
                    return $priceResponse->json();
                }
                
                return null;
            });

            if (!$priceData || !isset($priceData['data']['attributes'])) {
                return null;
            }

            $priceAttributes = $priceData['data']['attributes'];
            
            // Handle usage-based subscriptions differently
            if ($isUsageBased) {
                // For usage-based subscriptions, calculate MRR as quantity × unit_price
                // unit_price is often null for usage-based, so we use unit_price_decimal
                if (isset($priceAttributes['unit_price_decimal']) && $priceAttributes['unit_price_decimal'] !== null) {
                    $unitPrice = (float) ($priceAttributes['unit_price_decimal'] / 100);
                    // Multiply by quantity (number of displays) to get total MRR
                    return $unitPrice * max(1, $quantity); // Ensure at least 1
                }
                
                // Fallback: check for unit_price if available
                if (isset($priceAttributes['unit_price']) && $priceAttributes['unit_price'] !== null) {
                    $unitPrice = (float) ($priceAttributes['unit_price'] / 100);
                    // Multiply by quantity (number of displays) to get total MRR
                    return $unitPrice * max(1, $quantity); // Ensure at least 1
                }
                
                // Fallback: check for setup_price or other price fields
                if (isset($priceAttributes['setup_price']) && $priceAttributes['setup_price'] !== null) {
                    $price = (float) ($priceAttributes['setup_price'] / 100);
                    return $price;
                }
            } else {
                // For non-usage-based subscriptions, get unit_price (fixed monthly price)
                // Try unit_price_decimal first (more precise)
                if (isset($priceAttributes['unit_price_decimal']) && $priceAttributes['unit_price_decimal'] !== null) {
                    $price = (float) ($priceAttributes['unit_price_decimal'] / 100);
                    
                    // Check billing interval from price attributes
                    $interval = strtolower($priceAttributes['renewal_interval_unit'] ?? '');
                    $isYearly = $interval === 'year';
                    
                    // Convert yearly to monthly MRR
                    if ($isYearly) {
                        return $price / 12;
                    }
                    
                    return $price;
                }
                
                // Fallback to unit_price
                if (isset($priceAttributes['unit_price']) && $priceAttributes['unit_price'] !== null) {
                    $price = (float) ($priceAttributes['unit_price'] / 100);
                    
                    // Check billing interval from price attributes
                    $interval = strtolower($priceAttributes['renewal_interval_unit'] ?? '');
                    $isYearly = $interval === 'year';
                    
                    // Convert yearly to monthly MRR
                    if ($isYearly) {
                        return $price / 12;
                    }
                    
                    return $price;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
