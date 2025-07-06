<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\EventResource;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Services\DisplayService;
use App\Services\EventService;
use App\Services\OutlookService;
use App\Services\GoogleService;
use App\Services\CalDAVService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Http\Requests\API\EventBookRequest;

class EventController extends ApiController
{
    public function __construct(
        protected EventService $eventService,
        protected DisplayService $displayService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function index(): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($device->display_id, $device->id);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $events = $this->eventService->getEventsForDisplay($device->display_id);
            return $this->success(data: EventResource::collection($events));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Book a room for a given duration (Pro feature).
     */
    public function book(EventBookRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($device->display_id, $device->id, ['pro' => true, 'booking' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $data = $request->validated();
            $event = $this->eventService->bookRoom(
                $device->display_id,
                $device->user_id,
                (int) $request->duration,
                Arr::get($data, 'summary', __('Reserved'))
            );
            return $this->success(data: new EventResource($event), code: 201);
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return $this->error($e->getMessage(), $status);
        }
    }

    /**
     * Cancel an event (Pro feature).
     */
    public function cancel(string $eventId): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($device->display_id, $device->id, ['pro' => true]);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $this->eventService->cancelEvent($eventId, $device->display_id);
            return $this->success(message: 'Event cancelled successfully');
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return $this->error($e->getMessage(), $status);
        }
    }
}
