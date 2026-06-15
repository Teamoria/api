<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredApiKey = config('app.api_key');
        $providedApiKey = $request->header('x-api-key');

        if (
            ! is_string($configuredApiKey)
            || $configuredApiKey === ''
            || ! is_string($providedApiKey)
            || ! hash_equals($configuredApiKey, $providedApiKey)
        ) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Invalid API key.',
            'error_code' => 'UNAUTHENTICATED',
            'data' => [],
        ], 401);
    }
}
