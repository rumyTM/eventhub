---
description: Scaffold a full versioned API endpoint (route → FormRequest → controller → service → resource → tests) following CLAUDE.md.
argument-hint: <HTTP verb> <path> — e.g. "POST me/leads" or "GET products/{product}"
allowed-tools: Read, Grep, Glob, Edit, Write, Bash
---
> **Monorepo note.** This command operates inside a single Laravel service — either `services/core-api` or `services/payment-service`. `cd` into the right service first; read that service's `CLAUDE.md` (and the root `CLAUDE.md` for cross-service context) before generating.


Build a complete API endpoint for: **$ARGUMENTS**

Follow `CLAUDE.md` exactly. Before generating, read the existing route file, one sibling controller, one FormRequest,
one resource, and the response helper so names and the JSON envelope match this repo. Use `php artisan make:*`
(`--no-interaction`) to create files so namespaces/stubs are correct.

Steps:

1. **Clarify only if blocking** — infer the resource/model from the path; ask one question only if the model or
   intent is genuinely ambiguous.
2. **Route** — add it under the `v1` prefix in `routes/api.php`, in the right auth/middleware group, with a named
   throttle limiter. Add the limiter to `AppServiceProvider::configureRateLimiters()` if a new one is needed
   (with `->response()` returning the helper + `retry_after` in `data`).
3. **FormRequest** (for write/validated actions) — array rules, `messages()`, normalisation helpers if needed.
4. **Controller** — thin: `LogHelper::landingLog(...)` first line, `private readonly` **service** injection
   (never a repository directly), type-hint the FormRequest, pass `$request->validated()` to the service, return
   through a Resource wrapped in `ApiResponse`.
5. **Repository** — create/extend `{Model}RepositoryInterface` + its Eloquent impl for any query/persistence the
   feature needs; bind it in the repository provider. All Eloquent query building lives here, using model scopes.
6. **Service/Action** — orchestrate here: inject the repository **interface**, own the `DB::transaction()` for
   multi-write, dispatch jobs/events. No Eloquent queries in the service. Explicit types.
7. **Resource** — `whenLoaded()` for relations, enums as `{value, label}`, ISO-8601 timestamps.
8. **Tests** — happy path + 422 + 401 + 429 (where applicable), matching the repo's Pest/PHPUnit style; factories;
   `Http::fake()`/`Queue::fake()` for externals. The repo interface makes the service trivially unit-testable too.
9. **Finish** — run `composer format`, then `php artisan test --filter` on the new tests. Report what was created and
   the test result. Never put secrets/PAN/OTP/tokens in code — use `[PLACEHOLDER]`.
