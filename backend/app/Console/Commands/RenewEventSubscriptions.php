<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\OutlookAccount;
use App\Services\OutlookService;
use Illuminate\Console\Command;
use App\Enums\AccountStatus;

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
        $expiredSubscriptions = EventSubscription::with(['display.calendar', 'display.calendar.room'])
            ->where(function ($query) {
                $query->whereHas('outlookAccount', function ($query) {
                    $query->where('status', AccountStatus::CONNECTED);
                })->orWhereHas('googleAccount', function ($query) {
                    $query->where('status', AccountStatus::CONNECTED);
                });
            })
            ->expired()
            ->get();

        logger()->info('Renewing ' . $expiredSubscriptions->count() . ' expired subscriptions');
        foreach ($expiredSubscriptions as $expiredSubscription) {
            $display = $expiredSubscription->display;

            // Renew Outlook event subscription
            if ($expiredSubscription->outlookAccount) {
                $this->renewOutlookEventSubscription($expiredSubscription->outlookAccount, $display, $expiredSubscription, $outlookService);
            }
        }

        $newDisplays = Display::with(['calendar.room', 'calendar.outlookAccount', 'calendar.googleAccount'])
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->doesntHave('eventSubscriptions')
            ->get();

        logger()->info('Creating ' . $newDisplays->count() . ' new subscriptions');
        foreach ($newDisplays as $newDisplay) {
            $calendar = $newDisplay->calendar;

            // Create new Outlook event subscription
            if ($calendar->outlookAccount) {
                $this->createOutlookEventSubscription($calendar->outlookAccount, $newDisplay, $outlookService);
            }
        }
    }

    /**
     * @param OutlookAccount $outlookAccount
     * @param Display $display
     * @param EventSubscription $eventSubscription
     * @param OutlookService $outlookService
     */
    private function renewOutlookEventSubscription(OutlookAccount $outlookAccount, Display $display, EventSubscription $eventSubscription, OutlookService $outlookService): void
    {
        try {
            $outlookService->deleteEventSubscription($outlookAccount, $eventSubscription, false);
        } catch (\Exception $e) {
            $display->update([
                'status' => DisplayStatus::ERROR,
            ]);
            logger()->error('Error renewing subscription for display ' . $display->id . ': ' . $e->getMessage());
        }

        $this->createOutlookEventSubscription($outlookAccount, $display, $outlookService);
    }

    /**
     * @param OutlookAccount $outlookAccount
     * @param Display $display
     * @param OutlookService $outlookService
     * @return void
     */
    private function createOutlookEventSubscription(OutlookAccount $outlookAccount, Display $display, OutlookService $outlookService): void
    {
        try {
            $calendar = $display->calendar;

            if ($calendar->room) {
                $outlookService->createEventSubscriptionByUser($outlookAccount, $display, $calendar->room->email_address);
                return;
            }

            $outlookService->createEventSubscriptionByCalendar($outlookAccount, $display, $calendar->calendar_id);
        } catch (\Exception $e) {
            $display->update([
                'status' => DisplayStatus::ERROR,
            ]);
            logger()->error('Error creating subscription for display ' . $display->id . ': ' . $e->getMessage());
        }
    }
}
