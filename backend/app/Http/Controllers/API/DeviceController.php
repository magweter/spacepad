<?php

namespace App\Http\Controllers\API;

use App\Enums\DisplayStatus;
use App\Http\Requests\API\ChangeDisplayRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DeviceController extends ApiController
{
    public function me(): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();

        // Eager load display with its own settings and any linked profile's settings to avoid N+1 queries
        $device->load('display.settings', 'display.profile.settings');

        return $this->success(
            data: DeviceResource::make($device)
        );
    }

    public function changeDisplay(ChangeDisplayRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();
        $data = $request->validated();

        if (! $device->user_id) {
            return $this->error(
                message: 'Device is not associated with a user',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        $user = User::with('workspaces')->find($device->user_id);
        if (! $user) {
            return $this->error(
                message: 'User not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // Scope to the device's own workspace, so a tablet cannot be pointed at another
        // team's display. Devices paired before workspace_id was set fall back.
        $workspaceIds = $device->workspace_id
            ? collect([$device->workspace_id])
            : $user->workspaces->pluck('id');

        if ($workspaceIds->isEmpty()) {
            return $this->error(
                message: 'User is not a member of any workspace',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        $display = Display::query()
            ->whereIn('workspace_id', $workspaceIds)
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
