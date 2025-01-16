<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Http\Resources\API\DeviceResource;
use App\Http\Resources\API\UserResource;
use App\Models\Device;
use App\Models\User;
use App\Services\OutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AuthController extends Controller
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
                'uid' => $request->validated()['uid']
            ],[
                'user_id' => $connectedUserId,
                'uid' => $request->validated()['uid'],
                'name' => $request->validated()['name'],
            ]);

            return response()->json([
                'data' => [
                    'token' => $device->createToken('device-token')->plainTextToken,
                    'device' => DeviceResource::make($device),
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'code' => [
                'incorrect',
            ],
        ]);
    }
}
