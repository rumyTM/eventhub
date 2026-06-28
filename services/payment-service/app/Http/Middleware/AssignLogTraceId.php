<?php

namespace App\Http\Middleware;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns one correlation id per request at the very start of the pipeline, so every log line in
 * the request — and every queued job it dispatches — shares that id.
 *
 * - Reuses a valid incoming `Log-Trace-ID` header (so the id continues across a service boundary);
 *   otherwise mints a fresh UUID.
 * - Stores it in Context (auto-stamped onto all logs; auto-propagated into queued jobs).
 * - Echoes it back on the response header so a caller/reviewer can correlate client <-> server logs.
 *
 * Register at the front of the API group in bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->api(prepend: [\App\Http\Middleware\AssignLogTraceId::class]);
 *   })
 *
 * Canonical project stub — copied verbatim during /scaffold-service.
 */
final class AssignLogTraceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(LogHelper::TRACE_HEADER);
        $traceId = ($incoming && Str::isUuid($incoming)) ? $incoming : (string) Str::uuid();

        LogHelper::setTraceId($traceId);

        $response = $next($request);
        $response->headers->set(LogHelper::TRACE_HEADER, $traceId);

        return $response;
    }
}
