<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\OutlookAccount;
use App\Models\GoogleAccount;
use App\Services\OutlookService;
use App\Services\GoogleService;
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
    public function handle(OutlookService $outlookService, GoogleService $googleService): void
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

            // Renew Google event subscription
            if ($expiredSubscription->googleAccount) {
                $this->renewGoogleEventSubscription($expiredSubscription->googleAccount, $display, $expiredSubscription, $googleService);
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

            // Create new Google event subscription
            if ($calendar->googleAccount) {
                $this->createGoogleEventSubscription($calendar->googleAccount, $newDisplay, $googleService);
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
            report('Error renewing Outlook subscription for display ' . $display->id . ': ' . $e->getMessage());
        }

        $this->createOutlookEventSubscription($outlookAccount, $display, $outlookService);
    }

    /**
     * @param GoogleAccount $googleAccount
     * @param Display $display
     * @param EventSubscription $eventSubscription
     * @param GoogleService $googleService
     */
    private function renewGoogleEventSubscription(GoogleAccount $googleAccount, Display $display, EventSubscription $eventSubscription, GoogleService $googleService): void
    {
        try {
            $googleService->deleteEventSubscription($googleAccount, $eventSubscription, false);
        } catch (\Exception $e) {
            $display->update([
                'status' => DisplayStatus::ERROR,
            ]);
            report('Error renewing Google subscription for display ' . $display->id . ': ' . $e->getMessage());
        }

        $this->createGoogleEventSubscription($googleAccount, $display, $googleService);
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
            report('Error creating Outlook subscription for display ' . $display->id . ': ' . $e->getMessage());
        }
    }

    /**
     * @param GoogleAccount $googleAccount
     * @param Display $display
     * @param GoogleService $googleService
     * @return void
     */
    private function createGoogleEventSubscription(GoogleAccount $googleAccount, Display $display, GoogleService $googleService): void
    {
        try {
            $googleService->createEventSubscription($googleAccount, $display, $display->calendar->calendar_id);
        } catch (\Exception $e) {
            $display->update([
                'status' => DisplayStatus::ERROR,
            ]);
            report('Error creating Google subscription for display ' . $display->id . ': ' . $e->getMessage());
        }
    }
}
