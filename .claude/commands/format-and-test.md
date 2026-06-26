---
description: Format changed PHP with Pint and run the relevant tests — the definition-of-done check before finishing work.
allowed-tools: Bash, Read, Grep, Glob
---
> **Monorepo note.** This command operates inside a single Laravel service — either `services/core-api` or `services/payment-service`. `cd` into the right service first; read that service's `CLAUDE.md` (and the root `CLAUDE.md` for cross-service context) before generating.


Run this project's quality gate and report results concisely.

1. **Format**: run `composer format` if defined, else `vendor/bin/pint --dirty`. Report what was reformatted.
2. **Detect changes**: `git diff --name-only` (and `--staged`) to see which PHP files changed.
3. **Test**: run the narrowest meaningful subset first — `php artisan test --filter=<Name>` or the test file/dir that
   covers the changed code. If changes are broad, run `php artisan test`. Match the repo's runner (Pest/PHPUnit);
   use `--compact` if the repo uses it.
4. **Report**: formatting result, tests passed/failed with counts, and any failure's file + assertion. If anything
   fails, stop and surface it — do not weaken tests or skip the gate.

$ARGUMENTS
