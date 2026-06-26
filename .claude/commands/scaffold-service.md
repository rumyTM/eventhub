---
description: Bootstrap one EventHub service from its CLAUDE.md (Laravel core-api/payment-service, Node notification-service, or Next.js frontend) with the correct base structure and conventions wired in.
argument-hint: <service> — one of: core-api | payment-service | notification-service | frontend
allowed-tools: Read, Grep, Glob, Edit, Write, Bash
---

Scaffold the **$ARGUMENTS** service so it's ready for feature work, following its `CLAUDE.md` exactly.

1. Read the root `CLAUDE.md` and the target service's `CLAUDE.md` first. Confirm port, DB name, and inter-service auth
   from the root system map.
2. Create the base project **only if it doesn't exist yet**:
   - **core-api / payment-service:** `composer create-project laravel/laravel .` (Laravel 11), install Sanctum, then
     **copy the canonical convention stubs verbatim** — `cp -r ../../.claude/stubs/laravel/app/* app/` — which installs
     `app/Helpers/LogHelper.php` (UUID per-request trace id, `Log-Trace-ID` cross-service propagation, recursive
     redaction) and `app/Support/ApiResponse.php` (the `{success,data,message,errors}` envelope, metadata-only logging).
     `app/Http/Middleware/AssignLogTraceId.php` is copied too. Do **not** hand-write these — the stub is the source of
     truth. Then in `bootstrap/app.php`: prepend `AssignLogTraceId` to the `api` middleware group
     (`$middleware->api(prepend: [\\App\\Http\\Middleware\\AssignLogTraceId::class])`) and render exceptions
     through `ApiResponse`. Set up `RepositoryServiceProvider`, the `app/` layout from the service `CLAUDE.md`,
     `/api/v1` routes, named rate limiters, and a `composer format` (Pint) script.
   - **Trace propagation:** every outbound inter-service HTTP call attaches `LogHelper::traceHeaders()`
     (`->withHeaders(LogHelper::traceHeaders())`); core-api also includes `trace_id` (from `LogHelper::traceId()`) in
     every notification job payload so the Node service can log under the same id. This keeps ONE correlation id across
     the whole journey (request -> queue job -> payment-service -> webhook -> notify).
   - **notification-service:** Node + TypeScript + BullMQ + Express skeleton per its `CLAUDE.md` layout (queues/, jobs/,
     channels/, delivery/, http/), with lint + test scripts.
   - **frontend:** `create-next-app` (App Router, TS, Tailwind) + shadcn/ui, the `lib/api/` client, route groups, and
     TanStack Query provider.
3. Wire env: copy from the root `.env.example` keys relevant to this service (ports, DB, Redis, shared secrets — use
   `[PLACEHOLDER]` values, never real secrets).
4. Add a minimal health endpoint and confirm the service boots.
5. Run the formatter/linter and report the file tree created. Update `WORKLOG.md`.

Do not over-build — this is scaffolding only. Feature work follows via `/make-endpoint`, `/crud`, or the scoped skill.
