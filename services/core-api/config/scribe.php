<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;

return [
    'title' => 'EventHub Core API',

    'description' => 'REST API for the EventHub multi-vendor event ticketing and payout platform. '
        .'Vendors create events and sell tickets; attendees browse and buy; admins approve vendors, '
        .'manage refunds, and disburse payouts. Every financial operation is auditable, idempotent, '
        .'and resilient to partial failure.',

    'intro_text' => <<<'INTRO'
        ## Authentication

        Most write endpoints and all admin endpoints require a Sanctum **bearer token**.
        Obtain one by calling **POST /api/v1/auth/login** (or **register**); include it as:

        ```
        Authorization: Bearer {YOUR_AUTH_KEY}
        ```

        Public read endpoints (event catalog, ticket types) work without a token.

        ## Demo credentials (seeded)

        | Role     | Email                        | Password |
        |----------|------------------------------|----------|
        | Admin    | admin@eventhub.test          | password |
        | Vendor   | vendor@eventhub.test         | password |
        | Attendee | attendee@eventhub.test       | password |

        ## Idempotency

        Money-moving endpoints (checkout, refund, payout-execute) are idempotent.
        Pass a unique `Idempotency-Key` header on the checkout request; duplicate calls return the
        original result without re-executing the side effect.

        ## Response envelope

        Every response uses the same shape:

        ```json
        {
          "success": true,
          "message": "Human-readable summary",
          "data": {},
          "errors": null
        }
        ```

        Validation failures return HTTP 422 with `errors` as a field → messages map.
        Rate-limited responses return HTTP 429 with `data.retry_after` (seconds).

        <aside>Internal webhook endpoints (<code>/api/v1/internal/payments/*</code>) are excluded from
        these docs — they are called only by the payment-service and are protected by a shared-secret
        HMAC signature, never by user tokens.</aside>
        INTRO,

    'base_url' => config('app.url'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            // Internal webhook callbacks are payment-service → core-api only; not consumer-facing.
            // health + admin.ping are closure routes documented in .scribe/endpoints/custom.0.yaml.
            'exclude' => [
                'internal.*',
                'api/v1/internal/*',
                'health',
                'admin.ping',
            ],
        ],
    ],

    // Scribe serves docs as a Laravel route at GET /docs.
    'type' => 'laravel',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        // Most endpoints are public reads; authenticated ones carry @authenticated in their docblock.
        'default' => false,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        // Set SCRIBE_AUTH_KEY in .env to a real seeded token for live response calls.
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_BEARER_TOKEN}',
        'extra_info' => 'Obtain a token via **POST /api/v1/auth/login**. '
            .'Include it in every authenticated request: `Authorization: Bearer {token}`.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
        'php',
    ],

    // Postman collection generated to storage/app/scribe/collection.json (laravel type).
    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
        ],
    ],

    // OpenAPI spec generated to storage/app/scribe/openapi.yaml (laravel type).
    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [
            'info.version' => '1.0.0',
        ],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'General',
        // Role-based ordering mirrors the typical consumer journey.
        'order' => [
            'Public',
            'Auth',
            'Vendor',
            'Attendee',
            'Admin',
            'General',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [],

    'fractal' => [
        'serializer' => null,
    ],
];
