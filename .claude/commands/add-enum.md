---
description: Create a string-backed PHP enum with a label() method and wire it into the model cast and validation, per CLAUDE.md.
argument-hint: <EnumName> [case1 case2 ...] — e.g. "LeadStatus pending approved rejected"
allowed-tools: Read, Grep, Glob, Edit, Write, Bash
---
> **Monorepo note.** This command operates inside a single Laravel service — either `services/core-api` or `services/payment-service`. `cd` into the right service first; read that service's `CLAUDE.md` (and the root `CLAUDE.md` for cross-service context) before generating.


Create the **$ARGUMENTS** enum following CLAUDE.md.

1. `php artisan make:enum {Name} --no-interaction` (or create in `app/Enums/`).
2. Make it **string-backed** (`enum {Name}: string`). Cases in PascalCase, backing values in snake_case.
   Use int-backed only if mirroring an existing integer column.
3. Add a required `label(): string` using `match`, with localised `__()` keys.
4. Add domain predicate methods (`isTerminal()`, etc.) as `match` methods if the logic belongs to the enum.
5. Add `color()` / `dropdown()` **only** if an admin UI consumes this enum; an API-only enum needs just `label()`.
6. Wire it up:
   - Cast the column on the model in `casts()`: `'status' => {Name}::class`.
   - In any `in:` validation rule, derive values: `Rule::in(array_column({Name}::cases(), 'value'))`.
   - Ensure the migration stores a `string` column (not MySQL `ENUM`).
   - In Resources, output as `{ value, label }`.
7. Run `composer format`.
