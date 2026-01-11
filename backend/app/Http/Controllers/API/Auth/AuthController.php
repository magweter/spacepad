<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\ApiController;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AuthController extends ApiController
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $code = $request->validated()['code'];
        $uid = $request->validated()['uid'];
        $name = $request->validated()['name'] ?? 'Unknown';
        $connectedUserId = cache()->get("connect-code:$code");

        // Check if the code is a valid connect code
        if ($connectedUserId !== null) {
            $user = User::find($connectedUserId);
            $workspace = $user?->primaryWorkspace();

            $device = Device::firstOrCreate([
                'user_id' => $connectedUserId,
                'uid' => $uid,
            ],[
                'user_id' => $connectedUserId,
                'workspace_id' => $workspace?->id,
                'uid' => $uid,
                'name' => $name,
            ]);

            // Update workspace_id if device already existed but didn't have one
            if ($device->workspace_id === null && $workspace) {
                $device->update(['workspace_id' => $workspace->id]);
            }

            logger()->info('Device authentication successful', [
                'user_id' => $connectedUserId,
                'device_id' => $device->id,
                'device_uid' => substr($uid, 0, 8) . '...',
                'device_name' => $name,
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 100),
            ]);

            return $this->success(
                data: [
                    'token' => $device->createToken('device-token')->plainTextToken,
                    'device' => DeviceResource::make($device),
                ]
            );
        }

        logger()->warning('Device authentication failed - invalid connect code', [
            'code_prefix' => substr($code, 0, 3) . '...',
            'device_uid' => substr($uid, 0, 8) . '...',
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 100),
        ]);

        return $this->error(
            message: 'Code is incorrect.',
            errors: [
                'code' => [
                    'incorrect',
                ]
            ]
        );
    }
}
