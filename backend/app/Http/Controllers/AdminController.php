<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\Instance;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    /**
     * Check if the current request is authorized for admin access
     */
    private function checkAdminAccess(): void
    {
        $user = Auth::user();
        
        // Prevent access if impersonating
        if (session()->get('impersonating')) {
            abort(403, 'Cannot access admin panel while impersonating. Please stop impersonating first.');
        }
        
        // Check if current user is admin
        if (!$user || !$user->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }
    }

    public function index()
    {
        $this->checkAdminAccess();

        $activeDisplays = Display::where('status', DisplayStatus::ACTIVE)->count();
        $totalDisplays = Display::count();
        $totalBoards = Board::count();
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
            ->withCount('boards')
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
            ->withCount('boards')
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

        // All users for the users overview tab (paginated for performance)
        $search = request()->get('search');
        $allUsersQuery = User::query()
            ->withCount('displays')
            ->withCount('boards')
            ->with(['subscriptions' => function($query) {
                $query->where(function($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                });
            }]);
        
        if ($search) {
            $allUsersQuery->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $allUsers = $allUsersQuery
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('pages.admin', [
            'activeInstances' => $activeInstances,
            'activeDisplays' => $activeDisplays,
            'payingUsers' => $payingUsers,
            'allUsers' => $allUsers,
            'activeDisplaysCount' => $activeDisplays->count(),
            'totalDisplays' => $totalDisplays,
            'totalBoards' => $totalBoards,
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

    /**
     * Show user details page
     */
    public function showUser(User $user)
    {
        $this->checkAdminAccess();
        
        $admin = Auth::user();

        // Load user relationships for display
        $user->load([
            'outlookAccounts',
            'googleAccounts',
            'caldavAccounts',
            'displays',
            'devices',
            'workspaces',
            'subscriptions' => function($query) {
                $query->where(function($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })->orderByDesc('created_at');
            },
        ]);

        // Get subscription info
        $subscriptionInfo = null;
        if (!$user->is_unlimited && $user->subscriptions->isNotEmpty()) {
            $subscription = $user->subscriptions->first();
            $subscriptionData = $this->getSubscriptionData($subscription->lemon_squeezy_id, $user->displays->count());
            if ($subscriptionData) {
                $subscriptionInfo = [
                    'status' => $subscriptionData['status'] ?? null,
                    'price' => $subscriptionData['price'] ?? 0,
                    'mrr' => ($subscriptionData['price'] ?? 0) * $user->displays->count(),
                    'ends_at' => $subscription->ends_at,
                ];
            }
        }

        return view('pages.admin.user', [
            'user' => $user,
            'subscriptionInfo' => $subscriptionInfo,
        ]);
    }

    /**
     * Delete a user account and all associated data
     */
    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        $this->checkAdminAccess();
        
        $admin = Auth::user();

        // Prevent deleting yourself
        if ($user->id === $admin->id) {
            return redirect()->route('admin.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Confirm deletion
        $request->validate([
            'confirm_email' => ['required', 'email'],
        ]);

        if ($request->input('confirm_email') !== $user->email) {
            return back()->withErrors(['confirm_email' => 'Email confirmation does not match.']);
        }

        DB::transaction(function () use ($user, $admin) {
            // Delete all user's personal access tokens
            $user->tokens()->delete();

            // Delete displays and their related data first (before calendars/accounts)
            if ($user->displays) {
                foreach ($user->displays as $display) {
                    // Delete event subscriptions
                    $display->eventSubscriptions()->delete();
                    // Delete display settings
                    $display->settings()->delete();
                    // Delete events associated with this display
                    $display->events()->delete();
                    // Delete devices associated with this display
                    $display->devices()->delete();
                    $display->delete();
                }
            }

            // Delete devices (standalone devices not linked to displays)
            $user->devices()->delete();

            // Delete rooms
            $user->rooms()->delete();

            // Delete Outlook accounts and their calendars/events
            if ($user->outlookAccounts) {
                foreach ($user->outlookAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete Google accounts and their calendars/events
            if ($user->googleAccounts) {
                foreach ($user->googleAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete CalDAV accounts and their calendars/events
            if ($user->caldavAccounts) {
                foreach ($user->caldavAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete any remaining calendars directly linked to user (shouldn't happen, but safety check)
            // Note: Calendars are linked through accounts, not directly to users, so this is unlikely
            // Events are deleted through calendars above

            // Handle workspaces
            $ownedWorkspaces = $user->ownedWorkspaces()->get();
            foreach ($ownedWorkspaces as $workspace) {
                // Get other members (excluding the user being deleted)
                $otherMembers = $workspace->members()->where('user_id', '!=', $user->id)->get();
                
                if ($otherMembers->isNotEmpty()) {
                    // Find first admin or first member to transfer ownership
                    $newOwner = $otherMembers->first(function ($member) {
                        return $member->pivot->role === \App\Enums\WorkspaceRole::ADMIN->value;
                    }) ?? $otherMembers->first();
                    
                    if ($newOwner) {
                        // Transfer ownership
                        WorkspaceMember::where('workspace_id', $workspace->id)
                            ->where('user_id', $newOwner->id)
                            ->update(['role' => \App\Enums\WorkspaceRole::OWNER]);
                    }
                } else {
                    // No other members, delete the workspace and all its data
                    foreach ($workspace->displays as $display) {
                        $display->eventSubscriptions()->delete();
                        $display->settings()->delete();
                        $display->events()->delete();
                        $display->devices()->delete();
                        $display->delete();
                    }
                    $workspace->devices()->delete();
                    foreach ($workspace->calendars as $calendar) {
                        $calendar->events()->delete();
                        $calendar->delete();
                    }
                    $workspace->rooms()->delete();
                    WorkspaceMember::where('workspace_id', $workspace->id)->delete();
                    $workspace->delete();
                }
            }

            // Delete workspace memberships (user's membership in workspaces they don't own)
            WorkspaceMember::where('user_id', $user->id)->delete();

            // Note: Instances are system-wide (for self-hosted tracking), not user-specific
            // No need to delete instances when deleting a user

            // Cancel LemonSqueezy subscriptions (if any)
            // Note: This doesn't actually cancel them in LemonSqueezy, just removes the local reference
            // You might want to add API call to cancel subscriptions
            if (method_exists($user, 'subscriptions')) {
                $user->subscriptions()->delete();
            }

            // Finally, delete the user
            $user->delete();

            logger()->info('User account deleted by admin', [
                'deleted_user_id' => $user->id,
                'deleted_user_email' => $user->email,
                'deleted_by_admin_id' => $admin->id,
                'deleted_by_admin_email' => $admin->email,
            ]);
        });

        return redirect()->route('admin.index')
            ->with('success', "User account {$user->email} and all associated data have been permanently deleted.");
    }

    /**
     * Impersonate a user
     */
    public function impersonate(User $user): RedirectResponse
    {
        $this->checkAdminAccess();
        
        $admin = Auth::user();

        // Prevent impersonating yourself
        if ($admin->id === $user->id) {
            return redirect()->route('admin.index')
                ->with('error', 'You cannot impersonate yourself.');
        }

        // Store original admin ID in session
        session()->put('impersonating', true);
        session()->put('impersonator_id', $admin->id);

        // Clear any workspace selection from admin session - let impersonated user's workspace be selected
        session()->forget('selected_workspace_id');

        // Log in as the target user
        Auth::login($user);

        // Regenerate session and CSRF token to prevent session fixation
        session()->regenerate();
        session()->regenerateToken();

        logger()->info('Admin started impersonating user', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'impersonated_user_id' => $user->id,
            'impersonated_user_email' => $user->email,
        ]);

        return redirect()->route('dashboard')
            ->with('success', "You are now impersonating {$user->email}");
    }

    /**
     * Stop impersonating and return to admin account
     */
    public function stopImpersonating(): RedirectResponse
    {
        $impersonatorId = session()->get('impersonator_id');
        
        if (!$impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::find($impersonatorId);
        if (!$impersonator || !$impersonator->isAdmin()) {
            session()->forget(['impersonating', 'impersonator_id']);
            return redirect()->route('dashboard');
        }

        $impersonatedUser = Auth::user();

        // Clear impersonation session
        session()->forget(['impersonating', 'impersonator_id']);

        // Log back in as admin
        Auth::login($impersonator);

        // Regenerate session and CSRF token to prevent session fixation
        session()->regenerate();
        session()->regenerateToken();

        logger()->info('Admin stopped impersonating user', [
            'admin_id' => $impersonator->id,
            'admin_email' => $impersonator->email,
            'impersonated_user_id' => $impersonatedUser->id,
            'impersonated_user_email' => $impersonatedUser->email,
        ]);

        return redirect()->route('admin.index')
            ->with('success', 'Stopped impersonating user.');
    }
}
