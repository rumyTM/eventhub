<?php

namespace App\Http\Middleware;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inter-service auth (CLAUDE.md §F): every money route requires the shared secret core-api presents
 * as `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}`. No endpoint here is publicly reachable.
 *
 *   - missing/empty bearer token        -> 401
 *   - present but does not match secret  -> 403
 *
 * Compared with hash_equals (constant-time) to avoid leaking the secret via timing. The token lives
 * in config/env only — never in code, logs, or responses.
 */
final class EnsureServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '') {
            LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2a] EnsureServiceToken — NO bearer token in request (401)', [
                'url' => $request->fullUrl(),
            ]);
            abort(401, 'Unauthorized.'); // generic — never reveal the auth mechanism
        }

        $expected = config('services.payment.service_token');

        $match = is_string($expected) && $expected !== '' && hash_equals($expected, $provided);

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2a] EnsureServiceToken — token check', [
            'expected_set' => is_string($expected) && $expected !== '',
            'provided_len' => strlen($provided),
            'expected_len' => is_string($expected) ? strlen($expected) : 0,
            'match' => $match,
        ]);

        if (! $match) {
            abort(403, 'Forbidden.');
        }

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2a] EnsureServiceToken — token verified OK');

        return $next($request);
    }
}
