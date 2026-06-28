<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the payment-service → core-api callback (ADR-10). This route is NOT user-authenticated
 * (no Sanctum); it is a service callback verified by two independent factors:
 *
 *   1. a shared-secret **bearer token** (`CORE_API_BEARER_TOKEN`), and
 *   2. an **HMAC-SHA256 signature** of the RAW request body keyed by a SEPARATE secret
 *      (`CORE_API_WEBHOOK_SECRET`), compared to the `X-Signature` header.
 *
 * The HMAC is computed over `$request->getContent()` — the exact bytes the payment-service signed —
 * BEFORE any JSON parsing or re-encoding, so a re-serialized array can never change the digest.
 * Both checks use `hash_equals` (constant-time). Either failure → 401 and nothing downstream runs,
 * so a forged/replayed-with-tampering callback never reaches the settlement logic.
 */
final class VerifyPaymentWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedBearer = config('services.webhook.bearer_token');
        $providedBearer = $request->bearerToken();

        if (! is_string($expectedBearer) || $expectedBearer === ''
            || ! is_string($providedBearer) || ! hash_equals($expectedBearer, $providedBearer)) {
            abort(401, 'Unauthorized.'); // generic — never reveal which factor failed
        }

        $secret = config('services.webhook.secret');
        $provided = (string) $request->header('X-Signature', '');
        $computed = hash_hmac('sha256', $request->getContent(), is_string($secret) ? $secret : '');

        if (! is_string($secret) || $secret === '' || $provided === '' || ! hash_equals($computed, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
