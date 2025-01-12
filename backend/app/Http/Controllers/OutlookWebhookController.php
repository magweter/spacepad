<?php

namespace App\Http\Controllers;

use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use App\Services\GoogleService;
use App\Services\OutlookService;
use App\Models\EventSubscription;
use App\Models\Event;

class OutlookWebhookController extends Controller
{
    public function __construct(
        protected GoogleService $googleService,
        protected OutlookService $outlookService,
        protected EventService $eventService
    )
    {
    }

    /**
     * Handle incoming notifications from Microsoft Graph.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function handleNotification(Request $request): Response
    {
        logger()->info('Received webhook', $request->toArray());

        // Handle webhook validation request
        if ($request->has('validationToken')) {
            return response($request->validationToken, 200)
                ->header('Content-Type', 'text/plain');
        }

        $newSyncTimestamp = now();

        $notifications = $request->input('value', []);
        foreach ($notifications as $notification) {
            $subscriptionId = Arr::get($notification, 'subscriptionId');
            $resource = Arr::get($notification, 'resource');
            $resourceId = Arr::get($notification, 'resourceData.id');
            $changeType = Arr::get($notification, 'changeType');

            // Check for resource id
            if (!$resource || !$resourceId) {
                logger()->warning('Resource or ResourceData was missing from request body');
                continue;
            }

            // Find the corresponding subscription in the database
            $subscription = EventSubscription::with([
                'synchronization',
                'synchronization.sourceCalendar',
                'synchronization.targetCalendar',
            ])
                ->where('subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                logger()->warning('Subscription not found', ['subscriptionId' => $subscriptionId]);
                continue;
            }

            // Get the authenticated user's accounts
            $synchronization = $subscription->synchronization;
            $outlookAccount = $subscription->connectedAccount;
            $googleAccount = $synchronization->targetCalendar->connectedAccount;
            $targetCalendarId = $synchronization->targetCalendar->calendar_id;

            $existingEvent = Event::query()
                ->where('synchronization_id', $subscription->synchronization_id)
                ->where('source_event_id', $resourceId)
                ->first();

            // Creating
            if ($changeType === 'created' && !$existingEvent) {
                logger()->info('Creating event', [
                    'resource' => $notification,
                    'existingEvent' => $existingEvent?->toArray()
                ]);

                $event = $this->outlookService->fetchResource($outlookAccount, $resource);
                $this->eventService->createEventInGoogle(
                    $googleAccount,
                    $subscription->synchronization_id,
                    $targetCalendarId,
                    $event
                );
            }

            // Updating
            if ($changeType === 'updated') {
                $event = $this->outlookService->fetchResource($outlookAccount, $resource);
                if ($existingEvent) {
                    logger()->info('Updating event', [
                        'resource' => $notification,
                        'existingEvent' => $existingEvent?->toArray()
                    ]);
                    $this->eventService->updateEventInGoogle(
                        $googleAccount,
                        $existingEvent,
                        $targetCalendarId,
                        $event
                    );
                }

                // Search for correct series master id
                $seriesMasterId = Arr::get($event, 'seriesMasterId');
                if (Arr::get($event, 'type') === 'seriesMaster') {
                    $seriesMasterId = $resourceId;
                }

                // Update events in serie
                if ($seriesMasterId) {
                    logger()->info('Updating serie of events', [
                        'resource' => $notification,
                        'existingEvent' => $existingEvent?->toArray()
                    ]);

                    $events = $this->outlookService->fetchSerieInstances(
                        $outlookAccount,
                        $seriesMasterId,
                        $synchronization->getStartTime(),
                        $synchronization->getEndTime()
                    );
                    foreach ($events as $event) {
                        $existingSerieEvent = Event::query()
                            ->where('synchronization_id', $subscription->synchronization_id)
                            ->where('source_event_id', $event['id'])
                            ->first();
                        if (!$existingSerieEvent) {
                            continue;
                        }

                        $this->eventService->updateEventInGoogle(
                            $googleAccount,
                            $existingSerieEvent,
                            $targetCalendarId,
                            $event
                        );
                    }
                }
            }

            // Deleting
            if ($changeType === 'deleted' && $existingEvent) {
                logger()->info('Deleting event', [
                    'resource' => $notification,
                    'existingEvent' => $existingEvent->toArray()
                ]);

                $this->eventService->deleteEventInGoogle(
                    $googleAccount,
                    $existingEvent,
                    $targetCalendarId,
                    $existingEvent->target_event_id
                );
            }

            // Set new point to sync from
            $synchronization->update([
                'last_event_at' => $newSyncTimestamp,
            ]);
        }

        return response('Notification processed', 200);
    }
}
