<?php

namespace App\Console\Commands;

use App\Events\AccountConnectedNoDisplay;
use App\Events\DisplayCreatedNoDevice;
use App\Events\TrialDayThree;
use App\Events\TrialEndingSoon;
use App\Events\TrialEndingTomorrow;
use App\Events\TrialExpiredOrCancelled;
use App\Events\UserActivatedAfter24h;
use App\Events\UserInactive;
use App\Events\UserNotActivatedAfter24h;
use App\Events\UserPassive;
use App\Models\CalDAVAccount;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use LemonSqueezy\Laravel\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckMarketingTriggers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-marketing-triggers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user conditions and fire marketing email trigger events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking marketing triggers...');

        // Check users not activated after 24h
        $this->checkUsersNotActivatedAfter24h();

        // Check users activated after 24h
        $this->checkUsersActivatedAfter24h();

        // Check passive users (14 days no activity)
        $this->checkPassiveUsers();

        // Check inactive users (30 days no activity)
        $this->checkInactiveUsers();

        // Check trial expired or cancelled
        $this->checkTrialExpiredOrCancelled();

        // Check users with account but no display after 4h
        $this->checkAccountConnectedNoDisplay();

        // Check users with display but no device after 24h
        $this->checkDisplayCreatedNoDevice();

        // Check trial day 3
        $this->checkTrialDayThree();

        // Check trial ending in 7 days
        $this->checkTrialEndingSoon();

        // Check trial ending tomorrow
        $this->checkTrialEndingTomorrow();

        $this->info('Marketing triggers check completed.');

        return self::SUCCESS;
    }

    /**
     * Check users registered 24h ago but haven't created a display
     */
    private function checkUsersNotActivatedAfter24h(): void
    {
        $users = User::whereNull('deleted_at')
            ->where('created_at', '<=', now()->subHours(24))
            ->where('created_at', '>', now()->subHours(25))
            ->whereDoesntHave('displays')
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:user_not_activated_24h:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new UserNotActivatedAfter24h($user));
                Cache::put($cacheKey, true, now()->addDays(7)); // Prevent duplicate events for 7 days
                $this->line("Fired UserNotActivatedAfter24h for user {$user->email}");
            }
        }
    }

    /**
     * Check users who created their first display 24h ago
     */
    private function checkUsersActivatedAfter24h(): void
    {
        // Get users whose first display was created 24h ago
        $users = User::whereNull('deleted_at')
            ->where('created_at', '<=', now()->subHours(24))
            ->where('created_at', '>', now()->subHours(25))
            ->whereHas('displays')
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:user_activated_24h:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new UserActivatedAfter24h($user));
                Cache::put($cacheKey, true, now()->addDays(7)); // Prevent duplicate events for 7 days
                $this->line("Fired UserActivatedAfter24h for user {$user->email}");
            }
        }
    }

    /**
     * Check users with no activity for 14 days
     * Activity includes: user activity, device activity
     */
    private function checkPassiveUsers(): void
    {
        $cutoffDate = now()->subDays(14);
        $previousCutoffDate = now()->subDays(15);

        $users = User::whereNull('deleted_at')
            ->where(function ($query) use ($cutoffDate, $previousCutoffDate) {
                // User's last activity is within the window (or null)
                $query->where(function ($q) use ($cutoffDate, $previousCutoffDate) {
                    $q->whereNotNull('last_activity_at')
                        ->where('last_activity_at', '<=', $cutoffDate)
                        ->where('last_activity_at', '>', $previousCutoffDate);
                });
            })
            // And no devices with recent activity
            ->whereDoesntHave('devices', function ($q) use ($cutoffDate) {
                $q->whereNotNull('last_activity_at')
                    ->where('last_activity_at', '>', $cutoffDate);
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:user_passive:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new UserPassive($user));
                Cache::put($cacheKey, true, now()->addDays(7)); // Prevent duplicate events for 7 days
                $this->line("Fired UserPassive for user {$user->email}");
            }
        }
    }

    /**
     * Check users with no activity for 30 days
     * Activity includes: user activity, device activity
     */
    private function checkInactiveUsers(): void
    {
        $cutoffDate = now()->subDays(30);
        $previousCutoffDate = now()->subDays(31);

        $users = User::whereNull('deleted_at')
            ->where(function ($query) use ($cutoffDate, $previousCutoffDate) {
                // User's last activity is within the window (or null)
                $query->where(function ($q) use ($cutoffDate, $previousCutoffDate) {
                    $q->whereNotNull('last_activity_at')
                        ->where('last_activity_at', '<=', $cutoffDate)
                        ->where('last_activity_at', '>', $previousCutoffDate);
                });
            })
            // And no devices with recent activity
            ->whereDoesntHave('devices', function ($q) use ($cutoffDate) {
                $q->whereNotNull('last_activity_at')
                    ->where('last_activity_at', '>', $cutoffDate);
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:user_inactive:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new UserInactive($user));
                Cache::put($cacheKey, true, now()->addDays(7)); // Prevent duplicate events for 7 days
                $this->line("Fired UserInactive for user {$user->email}");
            }
        }
    }

    /**
     * Check users who connected an account 4h ago but haven't created a display
     */
    private function checkAccountConnectedNoDisplay(): void
    {
        $window = [now()->subHours(5), now()->subHours(4)];

        $outlookUserIds = OutlookAccount::whereBetween('created_at', $window)->pluck('user_id');
        $googleUserIds  = GoogleAccount::whereBetween('created_at', $window)->pluck('user_id');
        $caldavUserIds  = CalDAVAccount::whereBetween('created_at', $window)->pluck('user_id');

        $userIds = $outlookUserIds->merge($googleUserIds)->merge($caldavUserIds)->unique();

        $users = User::whereNull('deleted_at')
            ->whereIn('id', $userIds)
            ->whereDoesntHave('displays')
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:account_connected_no_display:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new AccountConnectedNoDisplay($user));
                Cache::put($cacheKey, true, now()->addDays(7));
                $this->line("Fired AccountConnectedNoDisplay for user {$user->email}");
            }
        }
    }

    /**
     * Check users who created a display 24h ago but haven't connected a device
     */
    private function checkDisplayCreatedNoDevice(): void
    {
        $users = User::whereNull('deleted_at')
            ->whereHas('displays', function ($q) {
                $q->where('created_at', '<=', now()->subHours(24))
                  ->where('created_at', '>', now()->subHours(25));
            })
            ->whereDoesntHave('devices')
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:display_created_no_device:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new DisplayCreatedNoDevice($user));
                Cache::put($cacheKey, true, now()->addDays(7));
                $this->line("Fired DisplayCreatedNoDevice for user {$user->email}");
            }
        }
    }

    /**
     * Check users on day 3 of their trial
     */
    private function checkTrialDayThree(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $users = User::whereNull('deleted_at')
            ->where('is_unlimited', false)
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_ON_TRIAL)
                      ->where('created_at', '<=', now()->subDays(3))
                      ->where('created_at', '>', now()->subDays(4));
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:trial_day_three:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new TrialDayThree($user));
                Cache::put($cacheKey, true, now()->addDays(7));
                $this->line("Fired TrialDayThree for user {$user->email}");
            }
        }
    }

    /**
     * Check users whose trial ends in 7-8 days
     */
    private function checkTrialEndingSoon(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $users = User::whereNull('deleted_at')
            ->where('is_unlimited', false)
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_ON_TRIAL)
                      ->where('trial_ends_at', '>=', now()->addDays(7))
                      ->where('trial_ends_at', '<', now()->addDays(8));
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:trial_ending_soon:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new TrialEndingSoon($user));
                Cache::put($cacheKey, true, now()->addDays(7));
                $this->line("Fired TrialEndingSoon for user {$user->email}");
            }
        }
    }

    /**
     * Check users whose trial ends tomorrow
     */
    private function checkTrialEndingTomorrow(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $users = User::whereNull('deleted_at')
            ->where('is_unlimited', false)
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_ON_TRIAL)
                      ->where('trial_ends_at', '>=', now()->addDay())
                      ->where('trial_ends_at', '<', now()->addDays(2));
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:trial_ending_tomorrow:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new TrialEndingTomorrow($user));
                Cache::put($cacheKey, true, now()->addDays(7));
                $this->line("Fired TrialEndingTomorrow for user {$user->email}");
            }
        }
    }

    /**
     * Check users with expired or cancelled trials
     */
    private function checkTrialExpiredOrCancelled(): void
    {
        if (config('settings.is_self_hosted')) {
            return; // Skip for self-hosted instances
        }

        // Get users whose subscriptions ended in the last 24 hours
        $users = User::whereNull('deleted_at')
            ->where('is_unlimited', false)
            ->whereHas('subscriptions', function ($query) {
                // Subscription ended in the last 24 hours
                $query->where('ends_at', '<=', now())
                    ->where('ends_at', '>', now()->subDay());
            })
            ->whereDoesntHave('subscriptions', function ($query) {
                // And they don't have any active subscriptions
                $query->where(function ($q) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                });
            })
            ->get();

        foreach ($users as $user) {
            $cacheKey = "marketing:trial_expired:{$user->id}";
            if (!Cache::has($cacheKey)) {
                event(new TrialExpiredOrCancelled($user));
                Cache::put($cacheKey, true, now()->addDays(7)); // Prevent duplicate events for 7 days
                $this->line("Fired TrialExpiredOrCancelled for user {$user->email}");
            }
        }
    }
}

