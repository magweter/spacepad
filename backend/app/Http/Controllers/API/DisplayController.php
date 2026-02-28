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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class DisplayController extends ApiController
{
    public function __construct(
        protected EventService $eventService,
        protected DisplayService $displayService,
        protected ImageService $imageService,
    ) {
    }

    public function index(): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        if (!$device->user_id) {
            return $this->success(data: []);
        }

        $user = User::find($device->user_id);
        if (!$user) {
            return $this->success(data: []);
        }

        // Get displays from all workspaces the user is a member of
        $workspaceIds = $user->workspaces->pluck('id');
        if ($workspaceIds->isEmpty()) {
            return $this->success(data: []);
        }

        $displays = Display::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->with('settings')
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

    public function getData(string $displayId): JsonResponse
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

        try {
            $startTime = microtime(true);
            $display = $this->displayService->getDisplay($displayId);
            $events = $this->eventService->getEventsForDisplay($displayId);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            logger()->info('Display data retrieved successfully', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'display_name' => $display->name ?? 'Unknown',
                'event_count' => count($events),
                'duration_ms' => $duration,
                'ip' => request()->ip(),
            ]);

            return $this->success(data: DisplayDataResource::make([
                'display' => $display,
                'events' => $events,
            ]));
        } catch (\Exception $e) {
            logger()->error('Failed to fetch display data', [
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'display_id' => $displayId,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
                'ip' => request()->ip(),
            ]);
            report($e);
            return $this->error(message: 'Something went wrong while fetching display data. Please try again later.', code: 500);
        }
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
                end: $end
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
            return $this->error(message: 'Room could not be booked. There may be conflicting events during this time period. Please try a different time or duration.', code: $status);
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
     * Serve display images (logo or background) for mobile app
     */
    public function serveImage(string $displayId, string $type)
    {
        /** @var Device $device */
        $device = auth()->user();

        // Validate that the device has access to this display
        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id);
        if (!$permission->permitted) {
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
