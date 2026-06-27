<?php

use App\Helpers\LogHelper;
use App\Http\Middleware\AssignLogTraceId;
use App\Http\Middleware\EnsureRole;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // One correlation id per request, auto-propagated across the queue + services.
        $middleware->api(prepend: [AssignLogTraceId::class]);

        // Role-based route gating.
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Shape every API error once into the canonical envelope with the real status code.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // non-API requests use the default handler
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    message: $e->getMessage(),
                    errors: $e->errors(),
                    status: 422,
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    message: __('api.errors.unauthenticated'), status: 401,
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    message: __('api.errors.forbidden'), status: 403,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    message: __('api.errors.not_found'), status: 404,
                ),
                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    message: __('api.errors.too_many_requests'),
                    status: 429,
                    data: ['retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60)],
                ),
                // Domain HTTP exceptions (e.g. TicketsUnavailableException → 409) carry their own status.
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    message: $e->getMessage() ?: __('api.errors.server_error'),
                    status: $e->getStatusCode(),
                ),
                // QueryException / anything unexpected: log full detail server-side, leak nothing.
                default => (function () use ($e) {
                    LogHelper::logEntry(LogHelper::LOG_ERROR, 'Unhandled exception: '.$e->getMessage(), [
                        'exception' => $e::class,
                        'file' => $e->getFile().':'.$e->getLine(),
                    ]);

                    return ApiResponse::error(message: __('api.errors.server_error'), status: 500);
                })(),
            };
        });
    })->create();
