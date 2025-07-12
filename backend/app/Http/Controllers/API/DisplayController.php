<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Resources\API\DisplayDataResource;
use App\Http\Resources\API\DisplayResource;
use App\Http\Resources\API\EventResource;
use App\Models\Device;
use App\Models\Display;
use App\Services\DisplayService;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;

class DisplayController extends ApiController
{
    public function __construct(
        protected EventService $eventService,
        protected DisplayService $displayService,
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
}
