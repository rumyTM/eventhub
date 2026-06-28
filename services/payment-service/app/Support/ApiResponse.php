<?php

namespace App\Support;

use App\Helpers\LogHelper;
use Illuminate\Http\JsonResponse;

/**
 * The single response helper for every API endpoint. Emits the canonical envelope with the real
 * HTTP status code:
 *
 *   { "success": bool, "data": mixed|null, "message": string, "errors": object|null }
 *
 * - success: outcome flag.
 * - data:    payload (resources, tokens, pagination meta, retry_after) or null.
 * - errors:  field-level validation failures only ({ "field": ["msg"] }), else null.
 *
 * Only response metadata (status + message) is logged — never the body, which may carry tokens or
 * PII. Canonical project stub — copied verbatim into each Laravel service during /scaffold-service.
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return self::make(success: true, message: $message, data: $data, errors: null, status: $status);
    }

    public static function error(
        string $message,
        array|object|null $errors = null,
        int $status = 400,
        mixed $data = null,
    ): JsonResponse {
        return self::make(success: false, message: $message, data: $data, errors: $errors, status: $status);
    }

    private static function make(
        bool $success,
        string $message,
        mixed $data,
        array|object|null $errors,
        int $status,
    ): JsonResponse {
        // Log metadata only — never the body (avoids leaking tokens/PII into logs).
        LogHelper::logEntry(
            logType: $success ? LogHelper::LOG_INFO : LogHelper::LOG_WARNING,
            msg: "API Response [{$status}]: {$message}",
        );

        return response()->json([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
