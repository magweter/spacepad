<?php

namespace App\Console\Commands;

use App\Models\EventSubscription;
use App\Services\OutlookService;
use Illuminate\Console\Command;

class DeleteEventSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-subscription {subscriptionId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a event subscription';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle(OutlookService $outlookService): void
    {
        $eventSubscription = EventSubscription::findOrFail($this->argument('subscriptionId'));

        $outlookService->deleteEventSubscription($eventSubscription->connectedAccount, $eventSubscription);
    }
}
