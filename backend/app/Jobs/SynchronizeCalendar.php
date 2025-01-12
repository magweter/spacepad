<?php

namespace App\Jobs;

use App\Enums\DisplayStatus;
use App\Models\Synchronization;
use App\Services\EventService;
use App\Services\GoogleService;
use App\Services\OutlookService;
use App\Models\GoogleAccount;
use App\Models\Event;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class SynchronizeCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Carbon $newSyncTimestamp;

    public function __construct(protected string $synchronizationId, protected bool $fullSync)
    {
        $this->newSyncTimestamp = now();
    }

    /**
     * Execute the job.
     *
     * @param GoogleService $googleService
     * @param OutlookService $outlookService
     * @param EventService $eventService
     * @return void
     * @throws Exception
     */
    public function handle(
        GoogleService $googleService,
        OutlookService $outlookService,
        EventService $eventService
    ): void
    {
        $synchronization = Synchronization::with(['sourceCalendar', 'targetCalendar', 'eventSubscriptions'])
            ->findOrFail($this->synchronizationId);

        $synchronization->update(['status' => DisplayStatus::SYNCING]);

        // Get the authenticated user's accounts
        $outlookAccount = $synchronization->sourceCalendar->connectedAccount;
        $googleAccount = $synchronization->targetCalendar->connectedAccount;

        $sourceCalendarId = $synchronization->sourceCalendar->calendar_id;
        $targetCalendarId = $synchronization->targetCalendar->calendar_id;

        // Fetch events from Outlook
        $outlookEvents = $outlookService->fetchEvents(
            $outlookAccount,
            $sourceCalendarId,
            $synchronization->getStartTime(),
            $synchronization->getEndTime()
        );

        $newSyncTimestamp = now();

        logger()->info('Got events from Outlook', ['count' => count($outlookEvents)]);

        // Remove entries who are not updated
        if ($this->fullSync && $synchronization->last_sync_at) {
            $outlookEvents = collect($outlookEvents)
                ->filter(fn ($e) => $synchronization->last_sync_at->lte($e['lastModifiedDateTime']))
                ->toArray();
        }

        // Process each event
        try {
            $this->processEachEvent($outlookEvents, $eventService, $googleAccount, $targetCalendarId);
        } catch (Exception $e) {
            logger()->error('Error while processing event', (array) $e);
        }

        // Handle deleted events
        if ($this->fullSync) {
            $googleEvents = $googleService->fetchEvents(
                $googleAccount,
                $targetCalendarId,
                $synchronization->getStartTime(),
                $synchronization->getEndTime()
            );
            $this->deleteMissingEvents(
                $eventService,
                $googleAccount,
                $targetCalendarId,
                $googleEvents
            );
        }

        // Create event subscription for event updates
        if (! $synchronization->eventSubscriptions->count()) {
            $outlookService->createEventSubscription($outlookAccount, $synchronization, $sourceCalendarId);
        }

        // Set new point to sync from
        $synchronization->update([
            'status' => DisplayStatus::ACTIVE,
            'last_sync_at' => $newSyncTimestamp,
            'last_event_at' => $newSyncTimestamp,
        ]);
    }

    /**
     * Delete missing events from Google Calendar.
     *
     * @param EventService $eventService
     * @param GoogleAccount $googleAccount
     * @param string $calendarId
     * @param array $googleEvents
     * @throws Exception
     */
    protected function deleteMissingEvents(
        EventService $eventService,
        GoogleAccount $googleAccount,
        string        $calendarId,
        array         $googleEvents
    ): void
    {
        foreach ($googleEvents as $googleEvent) {
            $event = Event::query()
                ->where('synchronization_id', $this->synchronizationId)
                ->where('target_event_id', $googleEvent['id'])
                ->first();

            if ($event) {
                continue;
            }

            try {
                $eventService->deleteEventInGoogle($googleAccount, $event, $calendarId, $googleEvent['id']);
            } catch (Exception $e) {
                logger()->error('Error while deleting event', (array) $e);
            }
        }
    }

    /**
     * @param mixed $outlookEvents
     * @param EventService $eventService
     * @param GoogleAccount $googleAccount
     * @param mixed $targetCalendarId
     * @return void
     * @throws Exception
     */
    private function processEachEvent(
        array $outlookEvents,
        EventService $eventService,
        GoogleAccount $googleAccount,
        mixed $targetCalendarId
    ): void
    {
        foreach ($outlookEvents as $outlookEvent) {
            $existingEvent = Event::query()
                ->where('synchronization_id', $this->synchronizationId)
                ->where('source_event_id', $outlookEvent['id'])
                ->first();

            if ($existingEvent) {
                // If the event exists, update it
                $eventService->updateEventInGoogle(
                    $googleAccount,
                    $existingEvent,
                    $targetCalendarId,
                    $outlookEvent
                );
            } else {
                // If it's a new event, create it in Google Calendar
                $eventService->createEventInGoogle(
                    $googleAccount,
                    $this->synchronizationId,
                    $targetCalendarId,
                    $outlookEvent
                );
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Synchronization::query()
            ->where('id', $this->synchronizationId)
            ->update([
                'status' => DisplayStatus::ACTIVE,
                'last_sync_at' => $this->newSyncTimestamp,
            ]);
    }
}
