<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\ChangeDisplayRequest;
use App\Http\Resources\API\DeviceResource;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function getMe(): DeviceResource
    {
        return DeviceResource::make(auth()->user());
    }

    public function changeDisplay(ChangeDisplayRequest $request): JsonResponse
    {
        $data = $request->validated();
        $device = auth()->user;

        $device->update([
            'display_id' => $data['display_id'],
        ]);

        $device->display->update([
            'status' => DisplayStatus::ACTIVE
        ]);

        return response()->json();
    }
}
