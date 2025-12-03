<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\ApiController;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Http\Resources\API\DeviceResource;
use App\Models\Device;
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
        $connectedUserId = cache()->get("connect-code:$code");

        // Check if the code is a valid connect code
        if ($connectedUserId !== null) {
            $device = Device::firstOrCreate([
                'user_id' => $connectedUserId,
                'uid' => $request->validated()['uid'],
            ],[
                'user_id' => $connectedUserId,
                'uid' => $request->validated()['uid'],
                'name' => $request->validated()['name'],
            ]);

            return $this->success(
                data: [
                    'token' => $device->createToken('device-token')->plainTextToken,
                    'device' => DeviceResource::make($device),
                ]
            );
        }

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
