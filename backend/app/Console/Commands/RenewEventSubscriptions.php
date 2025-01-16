<?php

namespace App\Console\Commands;

use App\Enums\SynchronizationStatus;
use App\Models\EventSubscription;
use App\Models\Synchronization;
use App\Services\OutlookService;
use Illuminate\Console\Command;

class RenewEventSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:renew-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew all expired event subscriptions';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(OutlookService $outlookService): void
    {
        $expiredSubscriptions = EventSubscription::with(['synchronization'])->expired()->get();
        foreach ($expiredSubscriptions as $expiredSubscription) {
            $outlookAccount = $expiredSubscription->connectedAccount;
            $synchronization = $expiredSubscription->synchronization;
            $sourceCalendarId = $synchronization->sourceCalendar->calendar_id;

            $outlookService->deleteEventSubscription($outlookAccount, $expiredSubscription, false);
            $outlookService->createEventSubscription($outlookAccount, $synchronization, $sourceCalendarId);
        }

        $nonExistingSubsSyncs = Synchronization::with(['sourceCalendar'])
            ->where('status', SynchronizationStatus::ACTIVE)
            ->doesntHave('eventSubscriptions')
            ->get();
        foreach ($nonExistingSubsSyncs as $nonExistingSubsSync) {
            $outlookAccount = $nonExistingSubsSync->sourceCalendar->connectedAccount;
            $sourceCalendarId = $nonExistingSubsSync->sourceCalendar->calendar_id;

            $outlookService->createEventSubscription($outlookAccount, $nonExistingSubsSync, $sourceCalendarId);
        }
    }
}
