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
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                return ApiException::notFound(
                    class_basename($previous->getModel())
                )->render($request);
            }

            return (new ApiException(
                message: 'The endpoint you are looking for does not exist.',
                httpCode: 404,
                internalCode: ApiException::NOT_FOUND,
            ))->render($request);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::validation($e->errors())->render($request);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::forbidden()->render($request);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::forbidden($e->getMessage())->render($request);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return (new ApiException(
                message: 'Unauthenticated. Please log in first.',
                httpCode: 401,
                internalCode: ApiException::UNAUTHENTICATED,
            ))->render($request);
        });

        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::conflict(
                'This entry already exists.'
            )->render($request);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::tooManyRequests()->render($request);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ApiException) {
                return null;
            }

            if (! $request->is('api/*')) {
                return null;
            }

            return ApiException::internal(
                app()->isLocal() ? $e->getMessage() : 'Something went wrong.'
            )->render($request);
        });
    })->create();
