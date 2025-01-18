<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\EventSubscription;
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
        $expiredSubscriptions = EventSubscription::with(['display'])->expired()->get();
        foreach ($expiredSubscriptions as $expiredSubscription) {
            $outlookAccount = $expiredSubscription->outlookAccount;
            $display = $expiredSubscription->display;
            $emailAddress = $display->calendar->room->email_address;

            $outlookService->deleteEventSubscription($outlookAccount, $expiredSubscription, false);
            $outlookService->createEventSubscription($outlookAccount, $display, $emailAddress);
        }

        $nonExistingSyncs = Display::with(['calendar.room'])
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->doesntHave('eventSubscriptions')
            ->get();
        foreach ($nonExistingSyncs as $nonExistingSync) {
            $outlookAccount = $nonExistingSync->calendar->outlookAccount;
            $emailAddress = $nonExistingSync->calendar->room->email_address;

            $outlookService->createEventSubscription($outlookAccount, $nonExistingSync, $emailAddress);
        }
    }
}
