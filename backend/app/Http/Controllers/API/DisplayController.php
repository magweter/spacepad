<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Requests\API\BookEventRequest;
use App\Http\Resources\API\DisplayDataResource;
use App\Http\Resources\API\DisplayResource;
use App\Http\Resources\API\EventResource;
use App\Models\Device;
use App\Models\Display;
use App\Services\DisplayService;
use App\Services\EventService;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

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

        $displays = Display::query()
            ->where('user_id', $device->user_id)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->get();

        return $this->success(data: DisplayResource::collection($displays));
    }

    public function getData(string $displayId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($displayId, $device->id);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $display = $this->displayService->getDisplay($displayId);
            $events = $this->eventService->getEventsForDisplay($displayId);
            return $this->success(data: DisplayDataResource::make([
                'display' => $display,
                'events' => $events,
            ]));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
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
            $event = $this->eventService->bookRoom(
                $displayId,
                $device->user_id,
                (int) $request->duration,
                Arr::get($data, 'summary', __('Reserved'))
            );
            return $this->success(data: new EventResource($event), code: 201);
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return $this->error(message: $e->getMessage(), code: $status);
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
            $this->eventService->checkInToEvent($eventId, $displayId);
            return $this->success(message: 'Checked in successfully');
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return $this->error(message: $e->getMessage(), code: $status);
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
            $this->eventService->cancelEvent($eventId, $displayId);
            return $this->success(message: 'Event cancelled successfully');
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return $this->error(message: $e->getMessage(), code: $status);
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
