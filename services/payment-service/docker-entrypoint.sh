#!/bin/sh
set -e

if [ ! -f /app/.env ] && [ -f /app/.env.example ]; then
    cp /app/.env.example /app/.env
fi

# Sync Docker env vars → .env.
php -r '
$lines = file("/app/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$out   = [];
foreach ($lines as $line) {
    if (preg_match("/^([A-Z][A-Z0-9_]*)=/", $line, $m)) {
        $runtime = getenv($m[1]);
        if ($runtime !== false && $runtime !== "") {
            $line = $m[1] . "=" . $runtime;
        }
    }
    $out[] = $line;
}
file_put_contents("/app/.env", implode("\n", $out) . "\n");
'

# Auto-generate APP_KEY if it is still missing/empty in .env.
if ! grep -q "^APP_KEY=base64:" /app/.env 2>/dev/null; then
    echo "[entrypoint] APP_KEY not set — generating..."
    php artisan key:generate --force
fi

exec "$@"
