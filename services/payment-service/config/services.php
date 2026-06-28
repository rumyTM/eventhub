<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inter-service auth (EventHub)
    |--------------------------------------------------------------------------
    | Shared secret core-api presents on every inbound money call, checked by
    | EnsureServiceToken. Lives in env only — never commit a real value.
    */

    'payment' => [
        'service_token' => env('PAYMENT_SERVICE_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound webhook to core-api (EventHub)
    |--------------------------------------------------------------------------
    | Where this service POSTs the terminal charge result (ADR-10). The bearer
    | token authenticates the caller; the webhook_secret is a SEPARATE key that
    | HMAC-signs the body (X-Signature) — so a leaked Authorization header can't
    | forge a signature. `callback_url` is a fixed, trusted endpoint — never
    | accepted from a request body (SSRF guard). Secrets live in env only.
    */

    'core_api' => [
        'callback_url' => env('CORE_API_WEBHOOK_URL'),
        'bearer_token' => env('CORE_API_BEARER_TOKEN'),
        'webhook_secret' => env('CORE_API_WEBHOOK_SECRET'),
    ],

];
