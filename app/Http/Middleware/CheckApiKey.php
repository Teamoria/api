<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredApiKey = config('api.key');
        $providedApiKey = $request->header('x-api-key');

        if (! is_string($configuredApiKey) || $configuredApiKey === '') {
            throw new RuntimeException('The API key is not configured.');
        }

        if (! is_string($providedApiKey) || $providedApiKey === '') {
            throw ApiException::unauthenticated(
                message: 'The x-api-key header is required.',
                internalCode: ApiException::MISSING_API_KEY,
            );
        }

        if (! hash_equals($configuredApiKey, $providedApiKey)) {
            throw ApiException::unauthenticated(
                message: 'The provided API key is invalid.',
                internalCode: ApiException::INVALID_API_KEY,
            );
        }

        return $next($request);
    }
}
