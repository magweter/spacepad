<?php

namespace App\Console\Commands;

use App\Services\OutlookService;
use Illuminate\Console\Command;

class CreateEventSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-subscription {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a event subscription';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(OutlookService $outlookService): void
    {
        $synchronization = Synchronization::with(['eventSubscriptions', 'targetCalendar'])
            ->findOrFail($this->argument('id'));

        // Get the authenticated user's accounts
        $outlookAccount = $synchronization->sourceCalendar->connectedAccount;
        $sourceCalendarId = $synchronization->sourceCalendar->calendar_id;

        $outlookService->createEventSubscription($outlookAccount, $synchronization, $sourceCalendarId);
    }
}
