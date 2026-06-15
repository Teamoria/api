<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function resolveForCache(mixed $resource): array
    {
        $data = is_array($resource) ? $resource : $resource->resolve();

        return json_decode(json_encode($data), true);
    }

    protected function successResponse(mixed $data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(
        string $message = 'Error',
        int $code = 400,
        mixed $error = null,
        ?string $errorCode = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => [],
            'error_code' => $errorCode,
        ];

        if ($error && ! app()->environment('production')) {
            $response['data'] = $error;
        }

        return response()->json($response, $code);
    }
}
