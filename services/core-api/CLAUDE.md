# core-api — EventHub Main Application (Laravel 11, PHP 8.4+)

> **Runtime:** Laravel's floor is PHP 8.2, but the committed `composer.lock` resolves Symfony 8.x (`php >= 8.4.1`),
> so this service requires **PHP 8.4.1+**. Docker uses `php:8.4-cli`; the local path needs 8.4+ (8.2/8.3 fail
> `composer install`). See root `CLAUDE.md` §5.

> Central orchestrator. Owns events, ticket types, orders/holds, attendees, vendors/KYC, payouts, admin, auth,
> and all cron jobs. Talks to payment-service over REST (shared secret + idempotency) and publishes notification
> jobs to Redis. The **engineering standards below are mandatory** — they are the source of truth for how code is
> structured here. Root context lives in [`../../CLAUDE.md`](../../CLAUDE.md).

> **AI tooling:** this service has **Laravel Boost** installed (dev-only). Use its MCP tools when working here —
> `search-docs` for version-accurate Laravel/package docs (don't guess APIs), plus DB-schema, app-info, last-error,
> log, and Tinker introspection. Boost's auto-generated guidelines are *advisory*; **this `CLAUDE.md` is authoritative**
> wherever they differ (layering, hybrid lock, ULID, ledger, response envelope). See ADR-22.

---

## A. Architecture & layering (strict)

```
Route → Middleware → FormRequest → Controller (thin) → Service/Action → Repository → Model → DB
                                                              ↓
                                                     JsonResource → ApiResponse
```

- **Controllers are thin.** Validate via a FormRequest type-hint, delegate to a Service/Action, wrap the result in a
  Resource, return an `ApiResponse`. They depend on **services only** — never a repository directly. No business
  logic, no query building.
- **Services** hold business logic + orchestration: call repositories, dispatch jobs/events, own the
  `DB::transaction()` boundary. Depend on a **repository interface**, never an Eloquent query builder.
- **Repositories** own all data access for an aggregate behind a `{Model}RepositoryInterface` + Eloquent impl bound
  in the container. Return models/collections/paginators — never arrays of presentation data, never a Resource, no
  business logic, no transactions. No generic `BaseRepository`, no empty pass-through repos.
- **Actions** — single-responsibility reusable logic, one public `handle()` (e.g. `CalculatePayout`,
  `HoldTickets`, `GenerateQrCode`). Prefer for pure computation reused across services.
- **Models** own schema concerns: relationships, `casts()`, query scopes, small state-check helpers
  (`isPublished()`, `allowsRefund()`). No business logic beyond that.
- **`DB::` only for `DB::transaction()`/`beginTransaction()`** (in a service). Never `DB::table()`.
- External integrations (payment-service client, notification publisher) sit behind a **Contract** in
  `app/Contracts/`, bound in a provider, fakeable in tests.

### Directory layout (`app/`)
```
Actions/{Domain}/      One public handle(). e.g. Orders/HoldTickets, Payouts/CalculateSettlement.
Contracts/             PaymentServiceContract, NotificationPublisherContract.
Enums/                 String-backed enums, one per concept. EventStatus, OrderStatus, KycStatus, PayoutStatus...
Exceptions/{Domain}/   Domain exceptions: TicketsUnavailableException, HoldExpiredException, BelowPayoutThreshold...
Http/
  Controllers/Api/V1/  {Resource}Controller.
  Requests/{Domain}/   {Action}Request FormRequests.
  Resources/           {Model}Resource.
  Middleware/          Role middleware (EnsureRole), logging, locale.
Jobs/                  Queued jobs touching external services. {Verb}{Noun}Job.
Models/
Repositories/Contracts/   {Model}RepositoryInterface.
Repositories/Eloquent/    {Model}Repository — ALL query/persistence.
Rules/                 Custom ValidationRule classes.
Services/{Domain}/     {Domain}Service. Business logic + orchestration.
Support/               ApiResponse, value objects, Money.
Providers/             RepositoryServiceProvider (bind interfaces), rate limiters.
```

## B. API responses
Use the one static helper `ApiResponse` (in `app/Support/`). Envelope: `{ success, data, message, errors }` with the
**real HTTP status code**. This class and `app/Helpers/LogHelper.php` are **canonical project stubs** — they are
copied verbatim from `.claude/stubs/laravel/` during `/scaffold-service`. Do not hand-write or fork them. `ApiResponse`
logs response *metadata only* (status + message), never the body, so tokens/PII never reach the logs.

**Log correlation:** the `AssignLogTraceId` middleware (also a stub) assigns one `trace_id` per request into Laravel's
`Context`, which auto-stamps every log line and auto-propagates across queued jobs. Keep ONE id across the whole
journey: attach `LogHelper::traceHeaders()` to every outbound call to payment-service, and include
`'trace_id' => LogHelper::traceId()` in every notification job payload (so the Node service logs under the same id). A
valid incoming `Log-Trace-ID` header is reused, so a webhook callback continues the original id. Never use a static
property for the trace id — it would leak across jobs in a long-running worker.
```php
ApiResponse::success(data: ['event' => new EventResource($event)], message: __('api.events.created'), status: 201);
ApiResponse::error(message: __('api.errors.not_found'), status: 404);
ApiResponse::error(message: 'Validation failed', errors: $validator->errors()->toArray(), status: 422);
```
`errors` = field-level validation only. `retry_after`/meta go in `data`. Never `response()->json()` directly.

## C. Global exception handling
Shape errors once in `bootstrap/app.php` → `withExceptions()`. Gate on `$request->is('api/*')`. Map
`ValidationException`→422, `AuthenticationException`→401, `AuthorizationException`→403,
`ThrottleRequestsException`→429, `ModelNotFoundException`/`NotFoundHttpException`→404 through the helper.
`QueryException`/unhandled `Throwable` → log full detail server-side, return a generic 500/503. Never leak SQL,
stack traces, paths, or class names. Services catch only expected domain exceptions (e.g. `HoldExpiredException`→409).

## D. Controllers, FormRequests, Resources, Repositories, Services, Actions, Enums, Models
Follow these exactly (condensed — the `laravel-api-endpoint` skill has full templates):

- **Controller:** `private readonly` service injection; `LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__)`
  first line (logs method/url/redacted payload under the request's UUID trace id); type-hint the FormRequest; pass `$request->validated()` (never `->all()`); return a Resource wrapped in
  `ApiResponse`; named arguments on multi-arg calls.
- **FormRequest:** array rules (not pipe strings), human `messages()` via `__()`, normalisation in named methods (not
  `rules()`). Derive `in:` from enums: `Rule::in(array_column(EventStatus::cases(), 'value'))`.
- **Resource:** enums output as `{ value, label }` (never bare scalar); relations only via `whenLoaded()`; ISO-8601
  timestamps; money cast explicitly; **all event datetimes returned in UTC ISO-8601 plus the event's IANA timezone**.
- **Repository:** interface in `Repositories/Contracts/`, Eloquent impl in `Repositories/Eloquent/`, bound in
  `RepositoryServiceProvider`. Intent-revealing methods (`availableForPurchase`, `lockForUpdate`), not `findBy(array)`.
- **Service:** constructor-inject interfaces; explicit return types; `DB::transaction()` for multi-write; throw domain
  exceptions for invalid states.
- **Enum:** string-backed, PascalCase cases / snake_case values, required `label()` via `match`, domain predicates as
  `match` methods (`allowsReapplication()`, `isTerminal()`). Cast on model via `casts()`; store as `string` column.
- **Model:** `casts()` method (not `$casts`); money as integer minor units (poisha) or `decimal:2`, never float;
  relationship return types; reused `where` chains → scopes; `SoftDeletes` for auditable/recoverable records.
  **Primary keys are ULIDs** — use the `HasUlids` trait on every model, `$table->ulid('id')->primary()` in migrations,
  and `foreignUlid('...')` for FKs (non-enumerable across tenants, time-sortable; see ADR-19).

## E. Routes & rate limiting
All under `/api/v1`, grouped by role with `auth:sanctum` + role middleware. RESTful names
(`index/show/store/update/destroy` + verbs like `checkout`, `checkIn`, `requestPayout`, `approve`, `refund`).
Named rate limiters in `AppServiceProvider::configureRateLimiters()` (`throttle:<name>`), each with `->response()`
returning `ApiResponse::error()` + `retry_after` in `data`. Never inline limits.

---

## F. EventHub domain model (the important part)

### Roles & auth
Sanctum token auth. Single `users` table with a `role` enum (`admin`, `vendor`, `attendee`) — **a backed enum, not
spatie/laravel-permission** (three fixed roles, one per user, no dynamic/granular permissions; see ADR-21). Do **not**
add spatie or a roles/permissions table. Vendor/attendee detail lives in `vendors` / `attendees` profile tables (1:1
with user). `EnsureRole` middleware guards route groups. **Ownership:**
a vendor may only read/write its own events, orders, payouts — enforce via policy/middleware that checks
`vendor_id === $request->user()->vendor->id`. Attendees may not hit admin or vendor-management routes.

### Core entities (see `docs/erd.md` for the full ERD)
`users` · `vendors` (kyc_status, payout_account, webhook_url) · `attendees` · `events` (vendor_id, status, timezone,
starts_at/ends_at in UTC, capacity) · `ticket_types` (event_id, kind, price, currency, quantity_total,
quantity_sold, sales_start/sales_end, group_size+group_discount) · `orders` (attendee_id, status, totals, currency,
idempotency_key) · `order_items` (ticket_type_id, qty, unit_price) · `ticket_holds` (order_id, ticket_type_id, qty,
expires_at) · `tickets` (issued per seat, qr_code, checked_in_at) · `payments` (order_id, gateway, status, ref) ·
`refunds` (payment_id, amount, reason, status) · `payouts` (vendor_id, gross, commission, net, status, batch_id) ·
`disputes` (order_id, reason, status, resolution) · `waitlist_entries` (event_id/ticket_type_id, attendee_id,
position) · `notifications` (mirror of delivery status) · `ledger_entries` (append-only financial audit) ·
`idempotency_keys`.

### Event lifecycle
`draft → published → ongoing → completed → cancelled`. Enforce legal transitions in `EventService` via an
`EventStatus` enum predicate (`canTransitionTo()`). Only `published`/`ongoing` events are purchasable. Cancelling a
published event must trigger refunds + notifications.

### Ticket types
`early_bird` (time-limited price window), `vip`, `general`, `group_bundle` (buy N at a discount). Each has its own
inventory (`quantity_total`/`quantity_sold`), price, currency, and availability window (`sales_start`/`sales_end`).
Pricing/availability resolution is an **Action** (`ResolveTicketPrice`) so it's unit-testable in isolation.

### Order processing — holds, locking, expiry (CRITICAL — unit tests required)
1. **Checkout start:** create an `order` (`status=pending`) + `ticket_holds` with `expires_at = now()+15min`.
2. **Oversell prevention (hybrid lock — ADR-07):** acquire a short-lived **Redis lock per ticket_type** (cuts
   contention; satisfies the "distributed" requirement) **and**, inside the checkout transaction, an authoritative
   **DB row lock** (`SELECT ... FOR UPDATE` on the ticket_type row) before checking/decrementing available inventory.
   The DB row lock is the correctness guard — oversell is impossible even if Redis is down (fall back to DB-only);
   Redis is an optimization, never the source of truth.
   Available = `quantity_total - quantity_sold - active_holds`, where **active_holds counts only non-expired holds**
   (`status='active' AND expires_at > now()`). Expiry is enforced at **read time**, not by the cron — so inventory
   frees exactly at the 15-min mark for new buyers regardless of when `ReleaseExpiredHolds` next runs (the cron is
   housekeeping/waitlist-trigger, never the source of truth for expiry; this avoids a hold blocking stock for up to
   ~20 min). Reject with `TicketsUnavailableException` (409) if insufficient. This is the single most-tested path —
   concurrent purchase attempts must never oversell.
3. **Payment:** core-api calls payment-service (idempotency key = order's key). Returns `pending`; the real result
   arrives via webhook.
4. **Webhook success:** mark order `paid`, convert holds → issued `tickets` (with QR), increment `quantity_sold`,
   write a `ledger_entry`, enqueue order-confirmation notification.
5. **Webhook failure / hold expiry:** release holds, return inventory, mark order `expired`/`failed`. The
   `ReleaseExpiredHolds` job (every 5 min) is the safety net; the webhook path also releases on failure.
6. **Idempotency:** a duplicate checkout with the same idempotency key returns the existing order, never a second one.

### Vendor onboarding & KYC
`KycStatus`: `pending → verified → rejected`. Vendors can't receive payouts until `verified`. Admin verifies/rejects;
each transition enqueues a vendor approval/rejection notification.

### Payout management (CRITICAL — unit tests required)
`CalculatePayout` action: `net = gross_sales − (gross_sales × commission_rate)`, commission_rate configurable
(platform default + per-vendor override). Enforce a **minimum payout threshold** — below it, the payout is not
created/queued and rolls into the next cycle. Payout flow: vendor requests (or daily batch creates) → admin approves
→ core-api queues a payout batch to payment-service → payment-service executes & reports back → mark `paid`, write
`ledger_entry`, notify vendor. **Never double-pay:** payouts carry an idempotency key + batch_id; the batch job marks
each vendor processed transactionally so a mid-batch crash doesn't re-pay completed vendors.
**Settlement timing (ADR-20):** an order's revenue is settled **only after its event is marked `completed`** — never
before — so a cancelled or no-show event never produces a paid-then-clawed-back vendor. Payouts carry a
`reserved_refund` amount and link the exact orders settled via `payout_items`.

### Refunds (policy lives here; execution in payment-service)
Time-based policy vs event `starts_at`: **>48h before → 100%**, **24–48h → 50%**, **<24h → 0%**. A refund is requested
**against an order** (optionally specific items/quantity) — the attendee **never specifies an amount**; the amount is
**auto-derived** = policy% × selected line totals. "Partial" means refunding a **subset of tickets**, not an arbitrary
sum. In-policy → **auto-approved + executed**; out-of-policy contest → opens a `dispute` an admin mediates (ADR-11).
Refund execution calls payment-service; result writes a `ledger_entry`.
**Event cancellation (vendor/admin):** all attendees refunded **100%** (policy-overridden); the refund is **funded by
debiting the vendor** (negative `clawback` ledger entry), and the **platform also refunds its commission** — it earns
nothing on a cancelled event (ADR-23).

### Admin
Platform analytics (total sales, active events, vendor count, GMV), vendor approval/rejection, dispute/refund queue.

---

## G. Background jobs & cron (each must be idempotent + fail-safe)
Register in `routes/console.php` / scheduler. Each job: process in chunks, mark items done **inside** the transaction
that does the work, and be safe to re-run.

| Job | Schedule | Behaviour |
|---|---|---|
| `ProcessPayoutBatch` | daily | Calc pending settlements, deduct commission, enforce threshold, queue to payment-service. Mark each vendor processed transactionally — **no double-pay on mid-batch failure**. |
| `SendEventReminders` | hourly | Events starting within 24h → enqueue reminder for ticket holders **not yet reminded** (track `reminded_at`). |
| `ReleaseExpiredHolds` | every 5 min | Release holds older than 15 min, return inventory, mark orders `expired`. Triggers waitlist processing. |
| `GenerateSalesReport` | daily | Aggregate daily sales per vendor + platform-wide; store for dashboard. |
| `ProcessWaitlist` | on ticket release | When holds expire / orders cancel, notify waitlisted attendees in position order. |

## H. Inter-service clients
- **PaymentServiceContract** (in `Contracts/`, impl uses Laravel HTTP client): `createCharge`, `refund`,
  `executePayout`. Always send `Idempotency-Key` + `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}`. Calls happen in
  queued jobs; `Http::fake()` in tests. Handle payment-service being down: order stays `pending`, job retries with
  backoff, never lost.
- **NotificationPublisherContract:** pushes jobs to the Redis queue the notification-service consumes. Payload schema
  is documented in `services/notification-service/CLAUDE.md` — keep them in sync.

## I. Testing (required)
Pest (project default). `RefreshDatabase`. **Required unit/feature coverage:**
- **Order processing:** ticket hold creation, hold expiry release, **concurrent purchase attempts don't oversell**
  (simulate parallel checkouts against limited inventory), idempotent re-checkout.
- **Payout calculation:** commission deduction math, minimum-threshold enforcement, per-vendor commission override.
- **Inventory management:** capacity limits, oversell prevention, hold/return accounting.
Per endpoint: happy path + 422 + 401 + 403 (ownership) + 429 (where limited). Factories/states; `Http::fake()` for
payment-service; `Queue::fake()` for notification jobs. Assert the envelope (`assertJsonPath('success', true)`).

## J. Security & data protection (PII / Bangladesh Bank aware)
core-api handles **no card data** (that's the payment-service's simulated concern), so it is **out of PCI-DSS scope** —
PCI governs cardholder data we never touch. What core-api *does* hold is KYC/PII and money records, so: validate every
input (`$request->validated()` only); never put a token, OTP, secret, credential, or KYC/PII (NID, TIN, bank account)
in code, logs, tests, or responses — use `[PLACEHOLDER]`; `LogHelper` redacts sensitive params; serve sensitive docs
via short-lived signed URLs. Flag any column storing more than necessary; consider Bangladesh Bank / data-privacy
obligations for stored customer/vendor data.

## K. Definition of done
Layering respected · required tests pass · `composer format` (Pint) clean · no secrets · `WORKLOG.md` updated.
Create files with `php artisan make:*` (`--no-interaction`) for correct stubs/namespaces.
