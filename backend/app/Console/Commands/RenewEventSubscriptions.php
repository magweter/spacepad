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
use Illuminate\Support\Str;

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
        // Handle expired subscriptions
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

        // Handle subscriptions ready for retry (after failed creation)
        $retrySubscriptions = EventSubscription::with(['display.calendar', 'display.calendar.room'])
            ->where(function ($query) {
                $query->whereHas('outlookAccount', function ($query) {
                    $query->where('status', AccountStatus::CONNECTED);
                })->orWhereHas('googleAccount', function ($query) {
                    $query->where('status', AccountStatus::CONNECTED);
                });
            })
            ->readyForRetry()
            ->get();

        logger()->info('Retrying ' . $retrySubscriptions->count() . ' failed subscriptions');
        foreach ($retrySubscriptions as $retrySubscription) {
            $display = $retrySubscription->display;

            // Retry Outlook event subscription
            if ($retrySubscription->outlookAccount) {
                $this->retryOutlookEventSubscription($retrySubscription->outlookAccount, $display, $retrySubscription, $outlookService);
            }

            // Retry Google event subscription
            if ($retrySubscription->googleAccount) {
                $this->retryGoogleEventSubscription($retrySubscription->googleAccount, $display, $retrySubscription, $googleService);
            }
        }

        // Create new subscriptions for displays without any
        $newDisplays = Display::with(['calendar.room', 'calendar.outlookAccount', 'calendar.googleAccount'])
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->doesntHave('eventSubscriptions')
            ->get();

        logger()->info('Creating ' . $newDisplays->count() . ' new subscriptions');
        $successCount = 0;
        $skipCount = 0;
        $failCount = 0;

        foreach ($newDisplays as $newDisplay) {
            try {
                $calendar = $newDisplay->calendar;

                // Skip if calendar is not loaded
                if (!$calendar) {
                    logger()->warning('Display has no calendar', ['display_id' => $newDisplay->id]);
                    $skipCount++;
                    continue;
                }

                // Create new Outlook event subscription
                if ($calendar->outlookAccount) {
                    $this->createOutlookEventSubscription($calendar->outlookAccount, $newDisplay, $outlookService);
                    $successCount++;
                }
                // Create new Google event subscription
                elseif ($calendar->googleAccount) {
                    $this->createGoogleEventSubscription($calendar->googleAccount, $newDisplay, $googleService);
                    $successCount++;
                }
                // Log if neither account type exists
                else {
                    logger()->warning('Calendar has no Outlook or Google account', [
                        'display_id' => $newDisplay->id,
                        'calendar_id' => $calendar->id,
                    ]);
                    $skipCount++;
                }
            } catch (\Exception $e) {
                // Catch any unexpected errors to prevent one failure from stopping the entire batch
                logger()->error('Unexpected error creating subscription for display', [
                    'display_id' => $newDisplay->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                report($e);
                $failCount++;
            }
        }

        logger()->info('Subscription creation complete', [
            'total' => $newDisplays->count(),
            'success' => $successCount,
            'skipped' => $skipCount,
            'failed' => $failCount,
        ]);
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
            $outlookAccount->update(['status' => AccountStatus::ERROR]);
            $display->update(['status' => DisplayStatus::ERROR]);
            report('Error deleting Outlook subscription for display ' . $display->id . ': ' . $e->getMessage());
            return;
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
            $googleAccount->update(['status' => AccountStatus::ERROR]);
            $display->update(['status' => DisplayStatus::ERROR]);
            report('Error deleting Google subscription for display ' . $display->id . ': ' . $e->getMessage());
            return;
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
                $eventSubscription = $outlookService->createEventSubscriptionByUser($outlookAccount, $display, $calendar->calendar_id);
            } else {
                $eventSubscription = $outlookService->createEventSubscriptionByCalendar($outlookAccount, $display, $calendar->calendar_id);
            }

            // If subscription was created successfully, ensure retry tracking is reset
            if ($eventSubscription) {
                $eventSubscription->resetRetry();
            }
        } catch (\Exception $e) {
            // Try to create a placeholder subscription record for retry tracking
            try {
                $subscription = EventSubscription::create([
                    'subscription_id' => 'pending_' . $display->id . '_' . now()->timestamp,
                    'resource' => $calendar->room ? "/users/{$calendar->calendar_id}/calendar/events" : "/me/calendars/{$calendar->calendar_id}/events",
                    'expiration' => null,
                    'notification_url' => config('services.azure_ad.webhook_url'),
                    'display_id' => $display->id,
                    'outlook_account_id' => $outlookAccount->id,
                    'retry_count' => 0,
                    'next_retry_at' => now()->addMinute(), // Retry in 1 minute
                ]);

                logger()->warning('Failed to create Outlook subscription, scheduled for retry', [
                    'display_id' => $display->id,
                    'error' => $e->getMessage(),
                    'next_retry' => $subscription->next_retry_at,
                ]);
            } catch (\Exception $dbError) {
                // If we can't even create the placeholder, log the error
                logger()->error('Failed to create Outlook subscription AND placeholder record', [
                    'display_id' => $display->id,
                    'subscription_error' => $e->getMessage(),
                    'database_error' => $dbError->getMessage(),
                ]);
            }

            // Don't mark account or display as ERROR - let retries handle it
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
            $calendar = $display->calendar;

            // Prevent resources and groups from creating a push notification, as it is not supported by Google (pushNotSupportedForRequestedResource)
            if ($calendar->room || Str::contains($calendar->calendar_id, ['group.calendar.google.com', 'resource.calendar.google.com'])) {
                return;
            }

            $eventSubscription = $googleService->createEventSubscription($googleAccount, $display, $calendar->calendar_id);

            // If subscription was created successfully, ensure retry tracking is reset
            if ($eventSubscription) {
                $eventSubscription->resetRetry();
            }
        } catch (\Exception $e) {
            // Try to create a placeholder subscription record for retry tracking
            try {
                $subscription = EventSubscription::create([
                    'subscription_id' => 'pending_' . $display->id . '_' . now()->timestamp,
                    'resource' => $calendar->calendar_id,
                    'expiration' => null,
                    'notification_url' => config('services.google.webhook_url'),
                    'display_id' => $display->id,
                    'google_account_id' => $googleAccount->id,
                    'retry_count' => 0,
                    'next_retry_at' => now()->addMinute(),
                ]);

                logger()->warning('Failed to create Google subscription, scheduled for retry', [
                    'display_id' => $display->id,
                    'error' => $e->getMessage(),
                    'next_retry' => $subscription->next_retry_at,
                ]);
            } catch (\Exception $dbError) {
                // If we can't even create the placeholder, log the error
                logger()->error('Failed to create Google subscription AND placeholder record', [
                    'display_id' => $display->id,
                    'subscription_error' => $e->getMessage(),
                    'database_error' => $dbError->getMessage(),
                ]);
            }

            // Don't mark account or display as ERROR - let retries handle it
            report('Error creating Google subscription for display ' . $display->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Retry creating an Outlook event subscription.
     */
    private function retryOutlookEventSubscription(
        OutlookAccount $outlookAccount,
        Display $display,
        EventSubscription $subscription,
        OutlookService $outlookService
    ): void {
        $calendar = $display->calendar;
        $retryCount = $subscription->retry_count;

        try {
            // Attempt to create the actual subscription
            if ($calendar->room) {
                $newSubscription = $outlookService->createEventSubscriptionByUser($outlookAccount, $display, $calendar->calendar_id);
            } else {
                $newSubscription = $outlookService->createEventSubscriptionByCalendar($outlookAccount, $display, $calendar->calendar_id);
            }

            // If successful, delete the placeholder
            if ($newSubscription) {
                $subscription->delete();

                logger()->info('Successfully created Outlook subscription after retry', [
                    'display_id' => $display->id,
                    'retry_count' => $retryCount + 1,
                ]);
            }
        } catch (\Exception $e) {
            // Increment retry and reschedule (will keep trying every 60 minutes indefinitely)
            $subscription->incrementRetry();

            logger()->error('Outlook subscription retry failed, will retry again', [
                'display_id' => $display->id,
                'retry_count' => $subscription->retry_count,
                'next_retry' => $subscription->next_retry_at,
                'error' => $e->getMessage(),
            ]);

            // Report to Sentry for tracking
            report($e);
        }
    }

    /**
     * Retry creating a Google event subscription.
     */
    private function retryGoogleEventSubscription(
        GoogleAccount $googleAccount,
        Display $display,
        EventSubscription $subscription,
        GoogleService $googleService
    ): void {
        $calendar = $display->calendar;
        $retryCount = $subscription->retry_count;

        try {
            // Attempt to create the actual subscription
            $newSubscription = $googleService->createEventSubscription($googleAccount, $display, $calendar->calendar_id);

            // If successful, delete the placeholder
            if ($newSubscription) {
                $subscription->delete();

                logger()->info('Successfully created Google subscription after retry', [
                    'display_id' => $display->id,
                    'retry_count' => $retryCount + 1,
                ]);
            }
        } catch (\Exception $e) {
            // Increment retry and reschedule (will keep trying every 60 minutes indefinitely)
            $subscription->incrementRetry();

            logger()->error('Google subscription retry failed, will retry again', [
                'display_id' => $display->id,
                'retry_count' => $subscription->retry_count,
                'next_retry' => $subscription->next_retry_at,
                'error' => $e->getMessage(),
            ]);

            // Report to Sentry for tracking
            report($e);
        }
    }
}
