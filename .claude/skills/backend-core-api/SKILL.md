---
name: backend-core-api
description: Work on the EventHub core-api Laravel service (events, ticket types, orders/holds, attendees, vendors/KYC, payouts, admin, auth, cron jobs). Use when the task touches anything under services/core-api — controllers, services, repositories, models, migrations, jobs, the order/checkout/hold logic, payout calculation, or inventory. Scopes you to the main application so you can contribute without reading the whole monorepo.
---

# Skill: backend-core-api

**Service boundary:** `services/core-api` only. This is the Laravel 11 orchestrator — the source of truth for events,
orders, inventory, vendors, payouts, and auth. It calls payment-service over REST and publishes notification jobs to
Redis, but it does **not** contain gateway logic or notification delivery (those are separate services).

## Become productive fast
1. Read `services/core-api/CLAUDE.md` — the authoritative standards + EventHub domain model. **Do not skip section F
   (domain) and section G (cron jobs).**
2. Read `../../docs/system-architecture.md` (API contracts) and `../../docs/erd.md` (schema).
3. Orient in code (once it exists): `routes/api.php`, one sibling `*Controller`/`*Request`/`*Resource`/`*Service` and
   its `Repositories/Contracts` + `Repositories/Eloquent` pair, `app/Support/ApiResponse.php`, and
   `RepositoryServiceProvider`. Copy the existing style exactly.

## Key files & patterns
- Layering is strict: **Controller -> Service -> Repository -> Model**. Use the `laravel-api-endpoint` skill or
  `/make-endpoint` / `/crud` to scaffold — they encode the templates.
- Primary keys are **ULIDs** (`HasUlids`, `foreignUlid`); never assume or expose sequential integer IDs.
- Money never float (`decimal:2`/minor units + currency). Enums string-backed with `label()`. Resources output enums
  as `{value,label}` and datetimes as UTC ISO-8601 + the event timezone.
- The high-risk paths (write tests as you go): **order holds + hybrid locking — Redis lock + authoritative DB row lock (ADR-07)** (`Actions/Orders/HoldTickets`,
  the checkout service), **payout calculation** (`Actions/Payouts/CalculatePayout`), **inventory accounting**.

## How to run tests
```
cd services/core-api
php artisan test --filter=Order      # focused
php artisan test                     # full suite
composer format                      # Pint, before finishing
```
Required coverage: order processing (hold, expiry, **concurrent oversell**), payout calc (commission, threshold),
inventory (capacity, oversell). Use factories, `Http::fake()` for payment-service, `Queue::fake()` for notifications.

## Responsibilities checklist
Events & lifecycle · ticket types & pricing windows · orders/holds/locking/expiry · attendee mgmt + QR check-in ·
vendor onboarding/KYC · payout calc + approval + batch · admin analytics & dispute/refund mediation · Sanctum auth +
role/ownership guards · the 5 cron jobs · inter-service clients behind Contracts.

## Refuse / fix these
Business logic or Eloquent queries in a controller; service querying Eloquent instead of via a repository interface;
raw `response()->json()`; `$request->all()`; float money; oversell without a lock; money operation without an
idempotency key; any PAN/OTP/token/secret in code or logs (use `[PLACEHOLDER]`).
