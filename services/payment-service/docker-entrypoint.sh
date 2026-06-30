#!/bin/sh
set -e

if [ ! -f /app/.env ] && [ -f /app/.env.example ]; then
    cp /app/.env.example /app/.env
fi

# Before starting the server, overwrite .env entries with the actual runtime
# environment variables injected by Docker / the orchestrator.
#
# Why: PHP's built-in server workers rebuild $_ENV as a CGI-style environment
# on each request, so Docker's `environment:` variables are not reliably
# accessible via $_ENV during HTTP handling.  Laravel dotenv reads .env at
# bootstrap, so writing the real values here — before any PHP process starts —
# is the correct, 12-factor-compliant approach (config from the environment,
# .env as the local fallback template).
#
# Only keys that already exist in .env are updated; no new keys are injected.
# PHP is used to handle values that contain special shell/sed characters (URLs).
php -r '
$lines = file("/app/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$out   = [];
foreach ($lines as $line) {
    if (preg_match("/^([A-Z][A-Z0-9_]*)=/", $line, $m)) {
        $runtime = getenv($m[1]);
        $line = ($runtime !== false) ? $m[1] . "=" . $runtime : $line;
    }
    $out[] = $line;
}
file_put_contents("/app/.env", implode("\n", $out) . "\n");
'

exec "$@"
