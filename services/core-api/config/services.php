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
    | payment-service (EventHub inter-service client)
    |--------------------------------------------------------------------------
    | Base URL + the shared-secret bearer core-api presents on every money call,
    | plus the default gateway to charge against (gateway selection at checkout
    | is a later concern). Secrets live in env only — never commit a real value.
    */

    'payment' => [
        'base_url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8001'),
        'service_token' => env('PAYMENT_SERVICE_TOKEN'),
        'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe_sim'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound payment webhook (payment-service → core-api callback)
    |--------------------------------------------------------------------------
    | The callback is authenticated by a shared-secret bearer token AND an
    | HMAC-SHA256 signature of the raw body keyed by a SEPARATE secret (ADR-10).
    | These MUST equal the payment-service's CORE_API_BEARER_TOKEN /
    | CORE_API_WEBHOOK_SECRET. Secrets live in env only — never commit a real value.
    */

    'webhook' => [
        'bearer_token' => env('CORE_API_BEARER_TOKEN'),
        'secret' => env('CORE_API_WEBHOOK_SECRET'),
    ],

];
