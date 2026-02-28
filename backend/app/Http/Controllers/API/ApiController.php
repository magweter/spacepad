<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function success(string $message = 'Success', mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', mixed $errors = null, int $code = 400): JsonResponse
    {
        // Log API errors for observability (skip 404s and auth errors to avoid noise)
        if ($code >= 500 || ($code >= 400 && $code < 404)) {
            logger()->warning('API error response', [
                'message' => $message,
                'code' => $code,
                'errors' => $errors,
                'route' => request()->route()?->getName(),
                'path' => request()->path(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
