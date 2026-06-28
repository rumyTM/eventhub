<?php

use App\Helpers\LogHelper;
use App\Http\Middleware\AssignLogTraceId;
use App\Http\Middleware\EnsureServiceToken;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // One correlation id per request; forwarded from core-api via the Log-Trace-ID header.
        $middleware->api(prepend: [AssignLogTraceId::class]);

        // Shared-secret guard for every money route (no endpoint here is publicly reachable).
        $middleware->alias([
            'service.token' => EnsureServiceToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    message: $e->getMessage(), errors: $e->errors(), status: 422,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    message: 'Resource not found.', status: 404,
                ),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    message: $e->getMessage() ?: 'Server error.', status: $e->getStatusCode(),
                ),
                default => (function () use ($e) {
                    LogHelper::logEntry(LogHelper::LOG_ERROR, 'Unhandled exception: '.$e->getMessage(), [
                        'exception' => $e::class,
                        'file' => $e->getFile().':'.$e->getLine(),
                    ]);

                    return ApiResponse::error(message: 'Server error.', status: 500);
                })(),
            };
        });
    })->create();
