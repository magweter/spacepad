<?php

namespace App\Http\Controllers\API;

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
        auth()->user()->update([
            'display_id' => $data['display_id'],
        ]);

        return response()->json();
    }
}
