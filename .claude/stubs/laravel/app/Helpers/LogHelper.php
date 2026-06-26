<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Structured logger with a single correlation id per API journey.
 *
 * The trace id is stored in Laravel's Context, which gives us the one property that matters here:
 * EVERY log line written during the journey carries the SAME id — and the id automatically travels
 * across the queue (Context is dehydrated into a queued job and rehydrated on the worker). Combined
 * with the AssignLogTraceId middleware (sets it once per request) and forwarding the `Log-Trace-ID`
 * header on outbound inter-service calls, one checkout flow keeps one id through:
 *
 *   core-api request  ->  queued payment job  ->  payment-service  ->  webhook callback  ->  notify job
 *
 * Why Context and not a static property: a static survives across jobs in a long-running queue
 * worker, so unrelated jobs would wrongly share an id. Context is reset/rehydrated per job and per
 * request (and flushed by Octane), so each journey gets its own id.
 *
 * Canonical project stub — copied verbatim into each Laravel service during /scaffold-service.
 */
class LogHelper
{
    public const LOG_INFO = 'info';
    public const LOG_EMERGENCY = 'emergency';
    public const LOG_CRITICAL = 'critical';
    public const LOG_NOTICE = 'notice';
    public const LOG_ERROR = 'error';
    public const LOG_WARNING = 'warning';
    public const LOG_DEBUG = 'debug';

    /** HTTP header used to propagate the correlation id between services. */
    public const TRACE_HEADER = 'Log-Trace-ID';

    /** Context key under which the correlation id lives (auto-included in every log line). */
    public const CONTEXT_KEY = 'trace_id';

    /** Keys that must never be written to logs (case-insensitive, recursive). */
    private const REDACT = [
        'password', 'password_confirmation', 'pin', 'otp',
        'card_no', 'cvv', 'token', 'access_token', 'secret', 'incoming_ip',
    ];

    /**
     * The correlation id for the current journey. Returns the id already in Context (set by the
     * middleware, or rehydrated into a queued job); lazily mints + stores a UUID if none exists yet
     * (e.g. an artisan command or a test). Idempotent — repeated calls return the same id.
     */
    public static function traceId(): string
    {
        $existing = Context::get(self::CONTEXT_KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::uuid();
        Context::add(self::CONTEXT_KEY, $id);

        return $id;
    }

    /** Set the journey's correlation id (called by AssignLogTraceId middleware). */
    public static function setTraceId(string $id): void
    {
        Context::add(self::CONTEXT_KEY, $id);
    }

    /** Headers to attach to an outbound inter-service HTTP call so the id crosses the boundary. */
    public static function traceHeaders(): array
    {
        return [self::TRACE_HEADER => self::traceId()];
    }

    public static function logEntry(string $logType = self::LOG_INFO, string $msg = '', mixed $context = null): void
    {
        $valid = [
            self::LOG_INFO, self::LOG_EMERGENCY, self::LOG_CRITICAL,
            self::LOG_NOTICE, self::LOG_ERROR, self::LOG_WARNING, self::LOG_DEBUG,
        ];

        if (! in_array($logType, $valid, true)) {
            $logType = self::LOG_INFO;
        }

        // Ensure the id exists in Context so it stamps this line and every later one in the journey.
        self::traceId();

        Log::{$logType}($msg, ['data' => self::redact($context)]);
    }

    /** Log an incoming request as the first line of a controller action. */
    public static function landingLog(Request $request, string $title): void
    {
        self::logEntry(self::LOG_INFO, $title, [
            'ip' => $request->header('X-Forwarded-For') ?? $request->ip() ?? 'N/A',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => self::redact($request->except(self::REDACT)),
        ]);
    }

    /** Recursively replace sensitive values with [REDACTED] before logging. */
    private static function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $v) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $value[$key] = '[REDACTED]';
            } elseif (is_array($v)) {
                $value[$key] = self::redact($v);
            }
        }

        return $value;
    }
}
