<?php

namespace App\Console\Commands;

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Console\Command;

class TriggerRegistrationWebhookForMissingNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:trigger-registration-webhook-missing-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger registration webhook for one user without first_name or last_name (oldest first)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('first_name')
                    ->orWhereNull('last_name');
            })
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$user) {
            $this->info('No users found without first_name or last_name.');
            return self::SUCCESS;
        }

        $this->info("Triggering registration webhook for user: {$user->email} (ID: {$user->id})");

        event(new UserRegistered($user));

        $this->info('Registration webhook triggered successfully.');

        return self::SUCCESS;
    }
}

