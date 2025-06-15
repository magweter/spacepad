<?php

namespace App\Traits;

use App\Data\ApiResponse;
use Illuminate\Http\JsonResponse;

trait RespondsWithApiResponse
{
    protected function respond(ApiResponse $response): JsonResponse
    {
        return response()->json($response, $response->status);
    }
} 