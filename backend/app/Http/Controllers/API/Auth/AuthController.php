<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\ApiController;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AuthController extends ApiController
{
    public function __construct(protected OutlookService $outlookService) {}

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

        // Atomically retrieve and invalidate the connect code. The payload carries the
        // workspace that was on screen when the code was generated, so the device lands
        // there rather than in whichever workspace happens to be the user's primary one.
        $payload = Workspace::pullConnectCode($code);
        $connectedUserId = $payload['user_id'] ?? null;

        // Check if the code is a valid connect code and user exists
        if ($connectedUserId !== null) {
            $user = User::find($connectedUserId);

            // Verify user exists before proceeding
            if (! $user) {
                logger()->warning('Device authentication failed - user not found', [
                    'user_id' => $connectedUserId,
                    'code_prefix' => substr($code, 0, 3).'...',
                    'device_uid' => substr($uid, 0, 8).'...',
                    'ip' => $request->ip(),
                ]);

                return $this->error(
                    message: 'Code is incorrect.',
                    errors: [
                        'code' => [
                            'incorrect',
                        ],
                    ]
                );
            }

            // Fall back to the primary workspace only for codes issued before this became
            // workspace-scoped, and re-validate membership either way.
            $workspace = $payload['workspace_id']
                ? $user->workspaces()->find($payload['workspace_id'])
                : null;
            $workspace ??= $user->primaryWorkspace();

            $device = Device::firstOrCreate([
                'user_id' => $connectedUserId,
                'uid' => $uid,
            ], [
                'user_id' => $connectedUserId,
                'workspace_id' => $workspace?->id,
                'uid' => $uid,
                'name' => $name,
            ]);

            // Re-pairing an existing device moves it to the workspace it was just paired
            // from — that is what the person holding the tablet asked for.
            $updateData = ['name' => $name];
            if ($workspace) {
                $updateData['workspace_id'] = $workspace->id;
            }
            $device->update($updateData);

            logger()->info('Device authentication successful', [
                'user_id' => $connectedUserId,
                'device_id' => $device->id,
                'device_uid' => substr($uid, 0, 8).'...',
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
            'code_prefix' => substr($code, 0, 3).'...',
            'device_uid' => substr($uid, 0, 8).'...',
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 100),
        ]);

        return $this->error(
            message: 'Code is incorrect.',
            errors: [
                'code' => [
                    'incorrect',
                ],
            ]
        );
    }
}
