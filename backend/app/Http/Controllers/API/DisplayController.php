<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Requests\API\BookEventRequest;
use App\Http\Resources\API\DisplayDataResource;
use App\Http\Resources\API\DisplayResource;
use App\Http\Resources\API\EventResource;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Services\DisplayService;
use App\Services\EventService;
use App\Services\ImageService;
use App\Support\LocalDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class DisplayController extends ApiController
{
    public function __construct(
        protected EventService $eventService,
        protected DisplayService $displayService,
        protected ImageService $imageService,
    ) {}

    public function index(): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        if (! $device->user_id) {
            return $this->success(data: []);
        }

        $user = User::find($device->user_id);
        if (! $user) {
            return $this->success(data: []);
        }

        // Scope to the device's own workspace, so the room picker never lists another
        // team's displays. Devices paired before workspace_id was set fall back.
        $workspaceIds = $device->workspace_id
            ? collect([$device->workspace_id])
            : $user->workspaces->pluck('id');

        if ($workspaceIds->isEmpty()) {
            return $this->success(data: []);
        }

        $displays = Display::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->with(['settings', 'profile.settings'])
            ->get();

        logger()->info('Display list requested', [
            'user_id' => $device->user_id,
            'device_id' => $device->id,
            'workspace_ids' => $workspaceIds->toArray(),
            'display_count' => $displays->count(),
            'ip' => request()->ip(),
        ]);

        return $this->success(data: DisplayResource::collection($displays));
    }

    public function getEvents(Request $request, string $displayId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        if ($request->query('date')) {
            $request->validate(['date' => 'date_format:Y-m-d']);
        }

        try {
            $date = $request->query('date')
                ? Carbon::parse($request->query('date'))->startOfDay()
                : null;

            $events = $this->eventService->getEventsForDisplay(
                $displayId,
                $date,
                LocalDay::tryFromRequest($request, $date)
            );

            return $this->success(data: EventResource::collection($events));
        } catch (\Exception $e) {
            report($e);

            return $this->error(message: 'Something went wrong while fetching events.', code: 500);
        }
    }

    public function getData(Request $request, string $displayId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id);
        if (! $permission->permitted) {
            logger()->warning('Display data access denied', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'reason' => $permission->message,
                'ip' => request()->ip(),
            ]);

            return $this->error(message: $permission->message, code: $permission->code);
        }

        $startTime = microtime(true);
        $exception = null;
        $display = null;
        $events = [];

        // Resolved once and passed on, so the window we log is provably the window we answered with.
        $requestedDay = LocalDay::tryFromRequest($request);
        $day = $requestedDay ?? LocalDay::serverDay(Carbon::now());

        try {
            $display = $this->displayService->getDisplay($displayId);
            $events = $this->eventService->getEventsForDisplay($displayId, null, $day);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        logger()->info('Display data fetched', [
            'user_id' => $device->user_id,
            'device_id' => $device->id,
            'display_id' => $displayId,
            'success' => $exception === null,
            'event_count' => (string) count($events),
            'display_name' => $display?->name ?? 'Unknown',
            'duration_ms' => $duration,
            'ip' => request()->ip(),
            // What the tablet said about its own day, and the window we answered with. Without this
            // a "wrong day of events" report is guesswork: you cannot tell an old app build (no
            // headers, server day) from a tablet in another timezone.
            'local_date_header' => $request->header('X-Local-Date'),
            'utc_offset_header' => $request->header('X-Utc-Offset'),
            'day_source' => $requestedDay !== null ? 'tablet' : 'server',
            'day_start' => $day->start->toIso8601String(),
            'day_end' => $day->end->toIso8601String(),
        ]);

        if ($exception !== null) {
            logger()->warning('Display data fetch failed', [
                'display_id' => $displayId,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            return $this->error(message: 'Something went wrong while fetching display data. Please try again later.', code: 500);
        }

        return $this->success(data: DisplayDataResource::make([
            'display' => $display,
            'events' => $events,
        ]));
    }

    /**
     * Book a room for a given duration (Pro feature).
     */
    public function book(BookEventRequest $request, string $displayId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id, ['pro' => true, 'booking' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $data = $request->validated();

            // Parse start and end times if provided, otherwise use duration
            $start = isset($data['start']) ? Carbon::parse($data['start'])->utc() : null;
            $end = isset($data['end']) ? Carbon::parse($data['end'])->utc() : null;
            $duration = isset($data['duration']) ? (int) $data['duration'] : null;

            logger()->info('Room booking requested', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'start' => $start?->toIso8601String(),
                'end' => $end?->toIso8601String(),
                'duration' => $duration,
                'summary' => Arr::get($data, 'summary', __('Reserved')),
                'ip' => request()->ip(),
            ]);

            $event = $this->eventService->bookRoom(
                displayId: $displayId,
                userId: $device->user_id,
                summary: Arr::get($data, 'summary', __('Reserved')),
                duration: $duration,
                start: $start,
                end: $end,
                description: Arr::get($data, 'description'),
                attendees: Arr::get($data, 'attendees', []),
            );

            logger()->info('Room booked successfully', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $event->id ?? null,
                'ip' => request()->ip(),
            ]);

            return $this->success(data: new EventResource($event), code: 201);
        } catch (\Exception $e) {
            logger()->error('Room booking failed', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => request()->ip(),
            ]);
            report($e);
            $status = $e->getCode() === 403 ? 403 : 400;
            $message = $status === 403
                ? $e->getMessage()
                : 'Room could not be booked. There may be conflicting events during this time period. Please try a different time or duration.';

            return $this->error(message: $message, code: $status);
        }
    }

    /**
     * Check in to an event (Pro feature).
     */
    public function checkIn(string $displayId, string $eventId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id, ['pro' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            logger()->info('Event check-in requested', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'ip' => request()->ip(),
            ]);

            $this->eventService->checkInToEvent($eventId, $displayId);

            logger()->info('Event check-in successful', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'ip' => request()->ip(),
            ]);

            return $this->success(message: 'Checked in successfully');
        } catch (\Exception $e) {
            logger()->error('Event check-in failed', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => request()->ip(),
            ]);
            $status = $e->getCode() === 403 ? 403 : 400;

            return $this->error(message: 'Could not check in to event. Please try again later.', code: $status);
        }
    }

    /**
     * Cancel an event (Pro feature).
     */
    public function cancel(string $displayId, string $eventId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id, ['pro' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            logger()->info('Event cancellation requested', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'ip' => request()->ip(),
            ]);

            $this->eventService->cancelEvent($eventId, $displayId);

            logger()->info('Event cancelled successfully', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'ip' => request()->ip(),
            ]);

            return $this->success(message: 'Event cancelled successfully');
        } catch (\Exception $e) {
            logger()->error('Event cancellation failed', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => request()->ip(),
            ]);
            $status = $e->getCode() === 403 ? 403 : 400;

            return $this->error(message: 'Event could not be cancelled. Please try again later.', code: $status);
        }
    }

    /**
     * Extend the current event's end time (Pro feature).
     */
    public function extend(Request $request, string $displayId, string $eventId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id, ['pro' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        $request->validate(['new_end' => 'required|date']);

        try {
            $newEnd = Carbon::parse($request->input('new_end'))->utc();

            logger()->info('Event extend requested', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'new_end' => $newEnd->toIso8601String(),
                'ip' => request()->ip(),
            ]);

            $this->eventService->extendEvent($eventId, $displayId, $newEnd);

            logger()->info('Event extended successfully', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'new_end' => $newEnd->toIso8601String(),
                'ip' => request()->ip(),
            ]);

            return $this->success(message: 'Event extended successfully');
        } catch (\Exception $e) {
            logger()->error('Event extend failed', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => request()->ip(),
            ]);
            $status = $e->getCode() === 403 ? 403 : 400;

            return $this->error(message: 'Event could not be extended. Please try again later.', code: $status);
        }
    }

    /**
     * Serve display images (logo or background) for mobile app
     */
    public function serveImage(string $displayId, string $type)
    {
        /** @var Device $device */
        $device = auth()->user();

        // Validate that the device has access to this display
        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id);
        if (! $permission->permitted) {
            abort(403, 'Access denied');
        }

        try {
            $display = $this->displayService->getDisplay($displayId);

            return $this->imageService->serveImage($display, $type);
        } catch (\Exception $e) {
            abort(404, 'Image not found');
        }
    }
}
