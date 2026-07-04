<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\CheckApiKey;
use App\Http\Middleware\CheckCompany;
use App\Http\Middleware\CheckRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '127.0.0.1',
        ]);
        $middleware->alias([
            'check-api-key' => CheckApiKey::class,
            'role' => CheckRole::class,
            'check-company' => CheckCompany::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*')
                || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                return ApiException::notFound(
                    class_basename($previous->getModel())
                )->render($request);
            }

            $message = $e->getMessage();

            if ($message !== '' && ! str_starts_with($message, 'The route ')) {
                return ApiException::notFound(message: $message)->render($request);
            }

            return ApiException::notFound(
                message: 'The requested API endpoint does not exist.',
            )->render($request);
        });

        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::validation($e->errors())->render($request);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $e->getMessage();

            return ApiException::forbidden(
                $message !== '' && $message !== 'Access Denied.'
                    ? $message
                    : 'You do not have permission to perform this action.',
            )->render($request);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $e->getMessage();

            return ApiException::forbidden(
                $message !== '' && $message !== 'This action is unauthorized.'
                    ? $message
                    : 'You do not have permission to perform this action.',
            )->render($request);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::unauthenticated()->render($request);
        });

        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::conflict(
                'A record with the same unique value already exists. Please use different information.',
            )->render($request);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::tooManyRequests()
                ->render($request)
                ->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::methodNotAllowed($request->method())
                ->render($request)
                ->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::payloadTooLarge()
                ->render($request)
                ->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $httpCode = $e->getStatusCode();
            $message = $httpCode >= 500 || $httpCode === 419
                ? null
                : ($e->getMessage() ?: null);

            return ApiException::fromHttpStatus(
                httpCode: $httpCode,
                message: $message,
                details: $httpCode >= 500
                    ? [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]
                    : null,
            )->render($request)->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if ($e instanceof ApiException) {
                return null;
            }

            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::internal(details: [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ])->render($request);
        });
    })->create();
