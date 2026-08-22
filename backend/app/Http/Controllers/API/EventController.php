<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\API\EventResource;
use App\Models\Device;
use App\Services\DisplayService;
use App\Services\EventService;
use App\Support\LocalDay;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends ApiController
{
    public function __construct(
        protected EventService $eventService,
        protected DisplayService $displayService,
    ) {}

    /**
     * @throws Exception
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        $permission = $this->displayService->validateDisplayPermission($device->display_id, $device->id);
        if (! $permission->permitted) {
            return $this->error(message: $permission->message, code: $permission->code);
        }

        try {
            $events = $this->eventService->getEventsForDisplay(
                $device->display_id,
                null,
                LocalDay::tryFromRequest($request)
            );

            return $this->success(data: EventResource::collection($events));
        } catch (Exception $e) {
            return $this->error(message: $e->getMessage(), code: 500);
        }
    }
}
