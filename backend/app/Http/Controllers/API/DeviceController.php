<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Requests\API\ChangeDisplayRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Device;
use App\Models\Display;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DeviceController extends ApiController
{
    public function me(): JsonResponse
    {
        return $this->success(
            data: DeviceResource::make(auth()->user())
        );
    }

    public function changeDisplay(ChangeDisplayRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();
        $data = $request->validated();

        $display = Display::query()
            ->where('user_id', $device->user_id)
            ->find($data['display_id']);

        if (! $display) {
            return $this->error(
                message: 'Display could not be found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        $device->update(['display_id' => $display->id]);
        $display->update(['status' => DisplayStatus::ACTIVE]);

        return $this->success(
            message: 'Successfully changed display.'
        );
    }
}
