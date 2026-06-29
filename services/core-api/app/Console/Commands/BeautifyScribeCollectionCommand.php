<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Post-process the Scribe-generated Postman collection:
 *  1. Pretty-print all embedded JSON request/response bodies.
 *  2. Declare collection-level variables (baseUrl, token, entityIds).
 *  3. Set collection-level Bearer auth to {{token}}.
 *  4. Inject Postman test scripts that auto-capture variables after key requests.
 *  5. Wire URL path-variable defaults to the captured collection variables.
 *
 * Run after `php artisan scribe:generate`:
 *   php artisan scribe:beautify-collection
 */
class BeautifyScribeCollectionCommand extends Command
{
    protected $signature = 'scribe:beautify-collection
                            {--path= : Absolute path to collection.json (defaults to Scribe\'s output path)}';

    protected $description = 'Post-process the Scribe Postman collection: beautify JSON, add variables, inject auto-capture test scripts';

    // ── Variable capture config ──────────────────────────────────────────────

    /** Request names whose 2xx response contains a bearer token to capture. */
    private const TOKEN_REQUESTS = ['Register', 'Login'];

    /**
     * Request name → [collectionVariable, dotted JS path inside the response].
     * The dotted path supports numeric indices: "data.payouts.0.id".
     */
    private const CAPTURE_MAP = [
        'Create event'                 => ['eventId',      'data.event.id'],
        'Create ticket type'           => ['ticketTypeId', 'data.ticket_type.id'],
        'Checkout'                     => ['orderId',      'data.order.id'],
        'Build payout batch (admin)'   => ['payoutId',     'data.payouts.0.id'],
        'List pending vendors (admin)' => ['vendorId',     'data.vendors.0.id'],
    ];

    /**
     * URL path-variable key → collection variable reference.
     * Applied to every request that carries a matching :key segment.
     */
    private const PATH_VAR_MAP = [
        'id'            => '{{eventId}}',       // /events/:id (update / delete / show)
        'event_id'      => '{{eventId}}',
        'ticketType_id' => '{{ticketTypeId}}',
        'order_id'      => '{{orderId}}',
        'vendor_id'     => '{{vendorId}}',
        'payout_id'     => '{{payoutId}}',
    ];

    /** Full collection-variable list (replaces whatever Scribe generated). */
    private const COLLECTION_VARS = [
        ['id' => 'baseUrl',       'key' => 'baseUrl',       'value' => 'http://localhost:8000', 'type' => 'string', 'name' => 'string'],
        ['id' => 'token',         'key' => 'token',         'value' => '',                 'type' => 'string', 'name' => 'string'],
        ['id' => 'eventId',       'key' => 'eventId',       'value' => '',                 'type' => 'string', 'name' => 'string'],
        ['id' => 'ticketTypeId',  'key' => 'ticketTypeId',  'value' => '',                 'type' => 'string', 'name' => 'string'],
        ['id' => 'orderId',       'key' => 'orderId',       'value' => '',                 'type' => 'string', 'name' => 'string'],
        ['id' => 'vendorId',      'key' => 'vendorId',      'value' => '',                 'type' => 'string', 'name' => 'string'],
        ['id' => 'payoutId',      'key' => 'payoutId',      'value' => '',                 'type' => 'string', 'name' => 'string'],
    ];

    // ── Command entry point ──────────────────────────────────────────────────

    public function handle(): int
    {
        $path = $this->option('path')
            ?? storage_path('app/private/scribe/collection.json');

        if (! file_exists($path)) {
            $this->error("Collection not found at: {$path}");
            $this->line('Run `php artisan scribe:generate` first.');

            return self::FAILURE;
        }

        $collection = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Could not parse collection.json: '.json_last_error_msg());

            return self::FAILURE;
        }

        // 1. Pretty-print embedded JSON strings.
        $bodyCount = 0;
        $this->beautifyBodies($collection, $bodyCount);
        $this->line("  ✓ Beautified {$bodyCount} JSON body/bodies.");

        // 2. Collection variables.
        $collection['variable'] = self::COLLECTION_VARS;
        $this->line('  ✓ Set '.count(self::COLLECTION_VARS).' collection variables.');

        // 3. Collection-level Bearer auth → {{token}}.
        $collection['auth'] = [
            'type'   => 'bearer',
            'bearer' => [
                ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
            ],
        ];
        $this->line('  ✓ Collection auth → Bearer {{token}}.');

        // 4 & 5. Test scripts + path variable defaults.
        $scriptCount = 0;
        $pathCount   = 0;
        $this->processItems($collection['item'], $scriptCount, $pathCount);
        $this->line("  ✓ Injected test scripts into {$scriptCount} request(s).");
        $this->line("  ✓ Patched {$pathCount} URL path variable(s).");

        file_put_contents(
            $path,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->info("Collection post-processed → {$path}");

        return self::SUCCESS;
    }

    // ── Step 1: JSON beautification ──────────────────────────────────────────

    private function beautifyBodies(array &$node, int &$count): void
    {
        foreach ($node as $key => &$value) {
            if (is_array($value)) {
                $this->beautifyBodies($value, $count);
                continue;
            }

            if (! is_string($value) || ! in_array($key, ['raw', 'body'], true)) {
                continue;
            }

            $trimmed = ltrim($value);
            if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
                continue;
            }

            $decoded = json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($pretty !== false && $pretty !== $value) {
                $value = $pretty;
                $count++;
            }
        }
    }

    // ── Steps 4 & 5: test scripts + path vars ───────────────────────────────

    /**
     * Recursively walk folders/sub-folders and process every leaf request item.
     */
    private function processItems(array &$items, int &$scriptCount, int &$pathCount): void
    {
        foreach ($items as &$item) {
            if (isset($item['item'])) {
                // Folder (group or subgroup) → recurse.
                $this->processItems($item['item'], $scriptCount, $pathCount);
                continue;
            }

            // Leaf request item.
            $name = $item['name'] ?? '';

            // Patch URL path variables.
            if (isset($item['request']['url']['variable'])) {
                foreach ($item['request']['url']['variable'] as &$pathVar) {
                    $key = $pathVar['key'] ?? '';
                    if (isset(self::PATH_VAR_MAP[$key])) {
                        $pathVar['value'] = self::PATH_VAR_MAP[$key];
                        $pathCount++;
                    }
                }
                unset($pathVar);
            }

            // Inject test script.
            $script = $this->buildTestScript($name);
            if ($script !== null) {
                $item['event'] = [
                    [
                        'listen' => 'test',
                        'script' => [
                            'type' => 'text/javascript',
                            'exec' => $script,
                        ],
                    ],
                ];
                $scriptCount++;
            }
        }
        unset($item);
    }

    /**
     * Return the JS exec lines for the given request name, or null if no script
     * is needed for that request.
     *
     * @return string[]|null
     */
    private function buildTestScript(string $name): ?array
    {
        // Token capture (register / login).
        if (in_array($name, self::TOKEN_REQUESTS, true)) {
            return [
                'const res = pm.response.json();',
                '',
                'if (pm.response.code === 200 || pm.response.code === 201) {',
                '    if (res && res.data && res.data.token) {',
                '        pm.collectionVariables.set("token", res.data.token);',
                '        console.log("token captured.");',
                '    }',
                '}',
            ];
        }

        // Entity-ID captures.
        if (isset(self::CAPTURE_MAP[$name])) {
            [$variable, $dottedPath] = self::CAPTURE_MAP[$name];

            $jsAccessor  = $this->dottedPathToJs('res', $dottedPath);
            $jsCondition = $this->dottedPathToGuard('res', $dottedPath);

            return [
                'const res = pm.response.json();',
                '',
                'if ((pm.response.code === 200 || pm.response.code === 201) && '.$jsCondition.') {',
                '    pm.collectionVariables.set("'.$variable.'", '.$jsAccessor.');',
                '    console.log("'.$variable.' captured: " + '.$jsAccessor.');',
                '}',
            ];
        }

        return null;
    }

    /**
     * Convert "data.event.id" → "res.data.event.id"
     * and "data.payouts.0.id" → "res.data.payouts[0].id"
     */
    private function dottedPathToJs(string $root, string $dottedPath): string
    {
        $accessor = $root;
        foreach (explode('.', $dottedPath) as $part) {
            $accessor .= is_numeric($part) ? "[{$part}]" : ".{$part}";
        }

        return $accessor;
    }

    /**
     * Build a JS guard expression that checks every intermediate node is truthy.
     * "data.event.id" → "res && res.data && res.data.event && res.data.event.id"
     */
    private function dottedPathToGuard(string $root, string $dottedPath): string
    {
        $conditions = [$root];
        $current    = $root;

        foreach (explode('.', $dottedPath) as $part) {
            $current   .= is_numeric($part) ? "[{$part}]" : ".{$part}";
            $conditions[] = $current;
        }

        return implode(' && ', $conditions);
    }
}
