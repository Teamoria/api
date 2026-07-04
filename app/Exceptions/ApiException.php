<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiException extends Exception
{
    public const BAD_REQUEST = 'BAD_REQUEST';

    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const MISSING_API_KEY = 'MISSING_API_KEY';

    public const INVALID_API_KEY = 'INVALID_API_KEY';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    public const CONFLICT = 'CONFLICT';

    public const PAYLOAD_TOO_LARGE = 'PAYLOAD_TOO_LARGE';

    public const INTERNAL_ERROR = 'INTERNAL_ERROR';

    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    public const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    private readonly string $internalCode;

    public function __construct(
        string $message,
        private readonly mixed $details = null,
        private readonly int $httpCode = 400,
        ?string $internalCode = null,
    ) {
        parent::__construct($message);

        $this->internalCode = $internalCode ?? self::codeForStatus($httpCode);
    }

    public static function badRequest(
        string $message = 'The request could not be processed. Please check the submitted data and try again.',
    ): static {
        return new static(
            message: $message,
            httpCode: 400,
            internalCode: self::BAD_REQUEST,
        );
    }

    public static function validation(array $errors): static
    {
        return new static(
            message: 'Some fields contain invalid values. Please review the errors and try again.',
            details: $errors,
            httpCode: 422,
            internalCode: self::VALIDATION_ERROR,
        );
    }

    public static function unauthenticated(
        string $message = 'Authentication is required to access this resource.',
        string $internalCode = self::UNAUTHENTICATED,
    ): static {
        return new static(
            message: $message,
            httpCode: 401,
            internalCode: $internalCode,
        );
    }

    public static function notFound(
        string $resource = 'Resource',
        ?string $message = null,
    ): static {
        return new static(
            message: $message ?? "{$resource} not found.",
            httpCode: 404,
            internalCode: self::NOT_FOUND,
        );
    }

    public static function forbidden(
        string $message = 'You do not have permission to perform this action.',
    ): static {
        return new static(
            message: $message,
            httpCode: 403,
            internalCode: self::FORBIDDEN,
        );
    }

    public static function methodNotAllowed(string $method): static
    {
        return new static(
            message: "The {$method} method is not supported for this endpoint.",
            httpCode: 405,
            internalCode: self::METHOD_NOT_ALLOWED,
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

    public static function payloadTooLarge(): static
    {
        return new static(
            message: 'The request is too large. Please reduce the file size or amount of submitted data.',
            httpCode: 413,
            internalCode: self::PAYLOAD_TOO_LARGE,
        );
    }

    public static function internal(
        string $message = 'An unexpected error occurred. Please try again later.',
        mixed $details = null,
    ): static {
        return new static(
            message: $message,
            details: $details,
            httpCode: 500,
            internalCode: self::INTERNAL_ERROR,
        );
    }

    public static function tooManyRequests(
        string $message = 'Too many requests. Please wait before trying again.',
    ): static {
        return new static(
            message: $message,
            httpCode: 429,
            internalCode: self::TOO_MANY_REQUESTS,
        );
    }

    public static function fromHttpStatus(
        int $httpCode,
        ?string $message = null,
        mixed $details = null,
    ): static {
        return new static(
            message: $message ?? self::messageForStatus($httpCode),
            details: $details,
            httpCode: $httpCode,
        );
    }

    public static function codeForStatus(int $httpCode): string
    {
        return match ($httpCode) {
            400 => self::BAD_REQUEST,
            401 => self::UNAUTHENTICATED,
            403 => self::FORBIDDEN,
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            409 => self::CONFLICT,
            413 => self::PAYLOAD_TOO_LARGE,
            422 => self::VALIDATION_ERROR,
            429 => self::TOO_MANY_REQUESTS,
            503 => self::SERVICE_UNAVAILABLE,
            default => $httpCode >= 500
                ? self::INTERNAL_ERROR
                : self::BAD_REQUEST,
        };
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json($this->buildResponse(), $this->httpCode);
    }

    public function report(): bool
    {
        return $this->httpCode < 500;
    }

    /**
     * @return array{
     *     success: false,
     *     message: string,
     *     error_code: string,
     *     data: mixed
     * }
     */
    private function buildResponse(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->internalCode,
            'data' => $this->resolveData(),
        ];
    }

    private function resolveData(): mixed
    {
        if ($this->details === null || $this->details === []) {
            return [];
        }

        if ($this->internalCode === self::VALIDATION_ERROR) {
            return $this->details;
        }

        if (config('app.debug')) {
            return $this->details;
        }

        return [];
    }

    private static function messageForStatus(int $httpCode): string
    {
        return match ($httpCode) {
            400 => 'The request could not be processed. Please check the submitted data and try again.',
            401 => 'Authentication is required to access this resource.',
            403 => 'You do not have permission to perform this action.',
            404 => 'The requested resource could not be found.',
            405 => 'This HTTP method is not supported for the requested endpoint.',
            409 => 'The request conflicts with the current state of the resource.',
            413 => 'The request is too large. Please reduce the file size or amount of submitted data.',
            419 => 'Your session has expired. Please authenticate and try again.',
            422 => 'Some fields contain invalid values. Please review the errors and try again.',
            429 => 'Too many requests. Please wait before trying again.',
            503 => 'The service is temporarily unavailable. Please try again later.',
            default => $httpCode >= 500
                ? 'An unexpected error occurred. Please try again later.'
                : 'The request could not be completed.',
        };
    }
}
