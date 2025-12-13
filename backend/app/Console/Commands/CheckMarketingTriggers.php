<?php

namespace App\Console\Commands;

use App\Events\TrialExpiredOrCancelled;
use App\Events\UserActivatedAfter24h;
use App\Events\UserInactive;
use App\Events\UserNotActivatedAfter24h;
use App\Events\UserPassive;
use App\Models\User;
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
            ->whereHas('displays', function ($query) {
                $query->where('created_at', '<=', now()->subHours(24))
                    ->where('created_at', '>', now()->subHours(25));
            })
            ->get()
            ->filter(function ($user) {
                // Only if this was their first display
                $firstDisplay = $user->displays()->orderBy('created_at')->first();
                return $firstDisplay && 
                       $firstDisplay->created_at->isAfter(now()->subHours(25)) &&
                       $firstDisplay->created_at->isBefore(now()->subHours(24));
            });

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
     */
    private function checkPassiveUsers(): void
    {
        $users = User::whereNull('deleted_at')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', now()->subDays(14))
            ->where('last_activity_at', '>', now()->subDays(15))
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
     */
    private function checkInactiveUsers(): void
    {
        $users = User::whereNull('deleted_at')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', now()->subDays(30))
            ->where('last_activity_at', '>', now()->subDays(31))
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

