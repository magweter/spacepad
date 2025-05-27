<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTrials extends Command
{
    protected $signature = 'users:cleanup-expired-trials';
    protected $description = 'Delete users who have not subscribed after their trial period';

    public function handle()
    {
        $this->info('Starting cleanup of expired trial users...');

        // Find users who:
        // 1. Are not within their trial period (created_at > 7 days ago)
        // 2. Don't have an active subscription
        // 3. Are not using a self-hosted instance
        $expiredUsers = User::query()
            ->where('created_at', '<', now()->subDays(7))
            ->whereDoesntHave('subscriptions', function ($query) {
                $query->where('ends_at', '>', now());
            })
            ->when(!config('settings.is_self_hosted'), function ($query) {
                $query->where('created_at', '<', now()->subDays(30));
            })
            ->get();

        $count = 0;

        foreach ($expiredUsers as $user) {
            try {
                DB::transaction(function () use ($user) {
                    // Delete all related data
                    $user->outlookAccounts()->delete();
                    $user->googleAccounts()->delete();
                    $user->caldavAccounts()->delete();
                    $user->displays()->delete();
                    $user->delete();

                    Log::info('Deleted expired trial user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'created_at' => $user->created_at,
                    ]);
                });

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to delete expired trial user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Successfully deleted {$count} expired trial users.");
    }
} 