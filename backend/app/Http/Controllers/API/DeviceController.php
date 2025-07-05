<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\ChangeDisplayRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Display;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function me(): DeviceResource
    {
        return DeviceResource::make(auth()->user());
    }

    public function changeDisplay(ChangeDisplayRequest $request): JsonResponse
    {
        $data = $request->validated();
        $device = auth()->user();

        $display = Display::query()
            ->where('user_id', auth()->user()->user_id)
            ->find($data['display_id']);

        if (! $display) {
            return response()->json(['message' => 'Display could not be found'], 404);
        }

        $device->update(['display_id' => $display->id]);
        $display->update(['status' => DisplayStatus::ACTIVE]);

        return response()->json();
    }
}
