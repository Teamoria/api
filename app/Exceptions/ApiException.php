<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiException extends Exception
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const CONFLICT = 'CONFLICT';

    public const INTERNAL_ERROR = 'INTERNAL_ERROR';

    public const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    public function __construct(
        string $message,
        private readonly mixed $details = null,
        private readonly int $httpCode = 400,
        private readonly ?string $internalCode = null,
    ) {
        parent::__construct($message);
    }

    public static function validation(array $errors): static
    {
        return new static(
            message: 'The given data was invalid.',
            details: $errors,
            httpCode: 422,
            internalCode: self::VALIDATION_ERROR,
        );
    }

    public static function notFound(string $resource = 'Resource'): static
    {
        return new static(
            message: "{$resource} not found.",
            httpCode: 404,
            internalCode: self::NOT_FOUND,
        );
    }

    public static function forbidden(string $message = 'Forbidden.'): static
    {
        return new static(
            message: $message,
            httpCode: 403,
            internalCode: self::FORBIDDEN,
        );
    }

    public static function conflict(string $message): static
    {
        return new static(
            message: $message,
            httpCode: 409,
            internalCode: self::CONFLICT,
        );
    }

    public static function internal(string $message = 'An unexpected error occurred.'): static
    {
        return new static(
            message: $message,
            httpCode: 500,
            internalCode: self::INTERNAL_ERROR,
        );
    }

    public static function tooManyRequests(string $message = 'Too Many Attempts.'): static
    {
        return new static(
            message: $message,
            httpCode: 429,
            internalCode: self::TOO_MANY_REQUESTS,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json($this->buildResponse(), $this->httpCode);
    }

    private function buildResponse(): array
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->internalCode,
            'data' => $this->resolveData(),
        ];

        return array_filter($response, fn ($v) => $v !== null);
    }

    private function resolveData(): mixed
    {
        if (empty($this->details)) {
            return null;
        }

        if ($this->internalCode === self::VALIDATION_ERROR) {
            return $this->details;
        }

        if (config('app.debug')) {
            return $this->details;
        }

        return null;
    }
}
