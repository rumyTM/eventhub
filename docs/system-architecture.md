# EventHub — System Architecture

> **Graded deliverable (Rubric 2: System Architecture & Design — 25%).**
> "Excellent" = clean justified service boundaries; documented inter-service auth, error handling, retry; schema with
> financial audit trails, deliberate soft-deletes, explained indexing; graceful partial-failure handling.
> Keep the diagrams and contract skeletons in sync with the implementation as the build evolves.

## 1. High-level architecture

```
                              ┌─────────────────────────┐
                              │   frontend (Next.js 14)  │   :3000
                              └───────────┬─────────────┘
                                          │ REST + Sanctum bearer (user)
                                          ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                       core-api  (Laravel 11, PHP 8.2+)            :8000     │
│  Events · Tickets · Orders/holds · Attendees · Vendors/KYC · Payouts ·     │
│  Admin · Auth · Cron jobs · Sales reports                                  │
└───────┬───────────────────────────┬────────────────────────┬──────────────┘
        │ REST (shared secret +      │ Redis queue            │ REST webhook
        │  Idempotency-Key)          │ (notification jobs)    │  callback (signed)
        ▼                            ▼                        ▲
┌────────────────────────┐   ┌─────────────────────────┐     │
│ payment-service :8001   │   │ notification-svc :8002  │     │
│ Stripe/PayPal sim ·     │   │ BullMQ workers · email  │     │
│ charge·refund·payout ·  │   │ (sim) · vendor webhooks │     │
│ idempotency             │   │ · retry/backoff · DLQ   │     │
└───────┬────────────────┘   └───────────┬─────────────┘     │
        └── webhook callback ─────────────┼── outbound ──► vendor URLs
                       (to core-api) ─────┘
   Infra:  MySQL :3306 (db-per-service; inventory oversell lock = DB row lock)   ·   Redis :6379 (queues + coordination)
```

### Service boundaries & justification

Three services + a frontend, split along **failure-domain and rate-of-change** lines — not arbitrarily. The test for
each split is: *does this concern fail differently, scale differently, or change for a different reason than the
core?*

- **core-api (the orchestrator & DB of record).** Owns the entire EventHub domain — events, ticket types, orders,
  holds, inventory, vendors/KYC, payouts, admin, auth, and all cron. It is deliberately a **modular monolith**, not a
  fan of micro-domains: orders, inventory, and payouts share transactional consistency (the oversell lock and the
  ledger live in one database), and splitting them would trade a local transaction for a distributed one — strictly
  worse for money correctness. The boundary is drawn around *what must be transactionally consistent together.*
- **payment-service (money/gateway isolation).** Split out because it changes for a different reason (new gateways,
  PCI surface) and must contain blast radius: it is the only service that touches gateway credentials and raw charge
  flows, so keeping it separate means a gateway integration bug or a credential-handling concern never sits in the
  same process as the order DB. It is **stateless about EventHub's domain** — it knows charges, refunds, payouts, and
  idempotency keys, not "events" or "vendors" beyond the IDs core-api passes. This makes gateways swappable behind
  `PaymentGatewayContract` and lets the money path scale and be hardened independently.
- **notification-service (slow/unreliable outbound I/O).** Split out because email and vendor webhooks are **slow,
  failure-prone, and bursty** — exactly the workload you never want on a synchronous checkout path. Behind a Redis
  queue with retry/backoff and a DLQ, a flaky vendor endpoint or a mail outage degrades *delivery latency*, never the
  ability to buy a ticket. Node + BullMQ is chosen here specifically for first-class retry/backoff/DLQ semantics.
- **frontend (Next.js).** A pure client of core-api over REST; holds no domain logic or DB. Separated so UI iteration
  never risks backend correctness.

What we deliberately **did not** split: orders, inventory, and payouts stay inside core-api precisely because they
require shared transactions and a single lock — the rubric penalizes a "monolith crammed into microservices," and a
distributed transaction across an `orders-service` and an `inventory-service` would be that mistake.

### Communication & data-flow matrix
| From → To | Protocol | Auth | Failure handling |
|---|---|---|---|
| frontend → core-api | REST `/api/v1` | Sanctum bearer (user) | Envelope `errors`/`message`; 401→login, 403, 429+retry_after |
| core-api → payment-service | REST | Shared secret + `Idempotency-Key` | Retry w/ backoff in a queued job; order stays `pending`; idempotent |
| payment-service → core-api | REST webhook | Shared secret + HMAC signature | Result persisted first; delivery retried; webhook handler idempotent |
| core-api → notification-service | Redis queue | Trusted network + queue name | Durable jobs; backlog never blocks checkout |
| notification-service → vendor | REST webhook | HMAC signature | Exponential backoff, max 5, dead-letter |

## 2. Authentication & authorization strategy

**User auth (frontend → core-api).** Laravel Sanctum personal-access tokens carried as `Authorization: Bearer`.
Every user has a `role` enum (`admin | vendor | attendee`); an `EnsureRole` middleware gates route groups by role,
and `php artisan`-style **policies** enforce the finer-grained checks. Tokens are issued at login and revoked at
logout; no session cookies for the API.

**Authorization is role + ownership, in two layers.** Role decides *which routes* you may call; ownership decides
*which rows*. A vendor may only read/mutate events, ticket types, orders, and payouts reachable through its own
`vendor_id` (enforced in policies, never in controllers); an attendee may only see/refund its **own** orders and is
blocked from all admin/vendor routes; admin-only actions (KYC decisions, commission settings, dispute resolution)
sit behind the admin role. Ownership checks are mandatory even on "obvious" routes so an authenticated vendor can
never reach another vendor's data by guessing IDs.

**Inter-service auth (server-to-server).** Every cross-service HTTP call carries a **static shared-secret bearer
token**, defined per service in `.env` (`PAYMENT_SERVICE_TOKEN`, etc.). Payment-service and notification-service
endpoints are **never publicly reachable** — they sit on the internal network and reject any request without the
shared secret. core-api → notification-service goes over the **Redis queue** (trusted network + queue name), not HTTP.

**Webhook integrity (replay-safe).** The payment-service → core-api callback is verified by **two** factors: the
shared-secret bearer token *and* an `X-Signature` header that is an **HMAC-SHA256 of the raw request body** keyed by
the shared secret. The signature (not just the bearer) is what survives replay/tampering — the handler recomputes the
HMAC over the received body and rejects on mismatch. Vendor-facing webhooks from notification-service are signed the
same way, keyed by each vendor's own `webhook_secret`.

**Rate limiting.** Sensitive routes sit behind **named rate limiters** — `auth` (login/register, to blunt
credential stuffing), `checkout`, and `refund` — each throttled per user/IP; an exceeded limit returns **HTTP 429**
with a `retry_after` value in `data` (per the envelope), not a buried 200.

**Secret hygiene.** All secrets live in environment variables only — never in code, logs, fixtures, or responses.
Docs and seed data use `[PLACEHOLDER]`; sensitive request fields are on the logging redaction list. See ADR-10 in the
[decision log](./technical-decision-log.md) for the auth rationale.

## 3. API contracts (key endpoints)
All core-api/payment-service responses use `{ success, message, data, errors }` + real HTTP status. Skeletons below;
expand and keep in sync with the implementation. Full endpoint list belongs in the API docs (Postman/OpenAPI).

### 3.1 Create event — `POST /api/v1/events` (vendor)
```jsonc
// Request
{ "title": "...", "description": "...", "timezone": "Asia/Dhaka",
  "starts_at": "2026-08-01T18:00:00+06:00", "ends_at": "2026-08-01T22:00:00+06:00",
  "ticket_types": [
    { "kind": "early_bird", "price": 5000, "currency": "BDT", "quantity_total": 100,
      "sales_start": "2026-07-01T00:00:00Z", "sales_end": "2026-07-15T00:00:00Z" }
  ] }
// Response 201
{ "success": true, "message": "Event created", "errors": null,
  "data": { "event": { "id": 1, "status": {"value":"draft","label":"Draft"}, "timezone": "Asia/Dhaka", "...": "..." } } }
```
**Rules.** Vendor-only (role + KYC `verified`). Validation: `title` required; `timezone` a valid IANA zone;
`ends_at > starts_at`; each ticket type needs `kind ∈ {early_bird,vip,general}`, integer `price ≥ 0` (poisha),
`quantity_total ≥ 1`, optional `sales_start/sales_end` with `sales_end > sales_start`. Group bundles are expressed as
`group_size` + `group_discount` on a ticket type, not a separate kind. **Defaults:** new event `status = draft`;
`currency = BDT`. **Ownership:** `vendor_id` is taken from the authenticated user, never the request body (no mass
assignment). **Partial-failure on ticket_types:** the event and all its ticket types are created in **one DB
transaction** — if any ticket type is invalid the whole create rolls back, so you never persist an event with a
half-written ticket-type set.

### 3.2 Purchase ticket (start checkout) — `POST /api/v1/orders/checkout` (attendee)
```jsonc
// Request  (Idempotency-Key header carried for the order)
{ "items": [ { "ticket_type_id": 12, "quantity": 2 } ] }
// Response 201 — hold created, payment pending
{ "success": true, "message": "Checkout started", "errors": null,
  "data": { "order": { "id": 99, "status": {"value":"pending"}, "currency": "BDT", "total": 10000,
            "hold_expires_at": "2026-06-27T12:15:00Z" },
            "payment": { "ref": "[PLACEHOLDER]", "status": "pending" } } }
// 409 if inventory insufficient (TicketsUnavailableException)
```
**Lock-and-decrement sequence (DB row lock).** Oversell is prevented with a **pessimistic DB row lock** —
`SELECT … FOR UPDATE` on each `ticket_type` row — taken *inside* the checkout transaction (see ADR-07):

```
BEGIN
  for each line item (ordered by ticket_type_id to avoid deadlocks):
    SELECT * FROM ticket_types WHERE id = ? FOR UPDATE         -- serialize concurrent buyers of this type
    available = quantity_total - quantity_sold - SUM(active holds)
    if available < requested:  ROLLBACK → 409 TicketsUnavailable
  create order (status=pending) + one ticket_hold per item (expires_at = now+15m), unit_price locked here
COMMIT                                                          -- row locks released on commit
```

The lock and the transaction **share one boundary**, so the lock auto-releases on commit/rollback — no orphaned
locks, no separate lock store to fail. `quantity_sold` is **not** decremented here; the hold is what reserves
inventory until payment.

**Idempotent re-checkout.** The client sends an `Idempotency-Key` header. If the same key arrives again (retry,
double-click), core-api returns the **same** order instead of creating a second one — the key is stored unique on
`orders`.

**What the client does next.** Checkout returns `order.status = pending` and `hold_expires_at`. core-api enqueues the
charge call to payment-service (a queued job, see §6); the client **polls** `GET /api/v1/orders/{id}` (or subscribes)
until status becomes `paid` (tickets issued) or `failed/expired`. **The webhook flips it:** payment-service's signed
callback moves the order `pending → paid`, increments `quantity_sold`, converts holds to issued QR tickets, writes
the `sale`/`commission` ledger entries, and enqueues the confirmation notification — all in one transaction.

**Gateway selection.** The attendee may pick a gateway at checkout (optional `gateway` field, e.g.
`stripe_sim`/`paypal_sim`); if omitted, core-api applies its configured default. That chosen value is what core-api
passes as `gateway` to payment-service `POST /payments`.

### 3.3 Process refund — `POST /api/v1/orders/{order}/refund` (attendee request / admin approve)
```jsonc
// Request
{ "amount": 5000, "reason": "..." }   // amount optional for full refund
// Response 200
{ "success": true, "message": "Refund processed", "errors": null,
  "data": { "refund": { "id": 5, "amount": 5000, "policy_applied": "50%_24_48h",
            "status": {"value":"completed"} } } }
```
**Policy resolution.** The refund percentage is resolved against the event's `starts_at` **UTC instant**: **>48h →
100%, 24–48h → 50%, <24h → 0%**. **Hybrid model:** an in-policy refund is **auto-approved and executed**; an
out-of-policy request (e.g. buyer contests the 0% window) does **not** refund — it opens a `dispute` for an admin to
mediate, who may then resolve it by issuing a refund (`disputes.refund_id`) or reject it.

**Full vs partial.** `amount` omitted = full refund of the remaining refundable balance; `amount` present = partial.
Either way the refund is validated against the **ledger** so cumulative refunded can never exceed the original
charge, and a ticket already `checked_in` is not refundable.

**Execution path.** core-api decides the policy/amount, then calls **payment-service `POST /refunds`** with a shared
secret + `Idempotency-Key` (so a retried refund never double-pays the buyer). On success it writes a signed
`refund` ledger entry. **Refund-after-payout:** if the revenue was already paid out, the refund also writes a negative
`clawback` ledger entry that offsets the vendor's next payout (balance may go negative — see [erd.md](./erd.md)).

**Two distinct endpoints.** This route is the **attendee's** refund *request* (auto-executes only when in-policy).
Resolving an out-of-policy **dispute** is a **separate admin endpoint** (e.g. `POST
/api/v1/admin/disputes/{dispute}/resolve`) — admin-only, operates on the `dispute`, and may issue the refund
(setting `disputes.refund_id`) or reject it. Keeping them separate cleanly splits the attendee and admin authorization
surfaces.

### 3.4 Calculate payout — `GET /api/v1/vendors/{vendor}/payouts/preview` (vendor/admin) + batch
```jsonc
// Response 200  (amounts in poisha; 500000 poisha = 5,000 BDT threshold)
{ "success": true, "message": "OK", "errors": null,
  "data": { "gross": 1000000, "commission_rate": 0.10, "commission": 100000, "net": 900000,
            "currency": "BDT", "meets_threshold": true, "threshold": 500000 } }
```

**Formula.** `net = gross − commission`, where `gross` = sum of paid, non-refunded order revenue attributable to the
vendor (from the **ledger**, not a mutable balance), and `commission = round_half_up(gross × commission_rate)`.
**Commission rate is snapshotted per order at sale time** (`orders.commission_rate`), so a payout sums each order's
own historical rate — changing the platform rate later never rewrites past payouts. A per-vendor override on
`vendors.commission_rate` takes precedence over the platform default *at sale time* (it's what gets snapshotted).

**Threshold rollover.** If the vendor's eligible `net` is below the **5,000 BDT (500,000 poisha)** minimum, no payout
is created — the balance simply remains in the ledger and **rolls into the next cycle** until it clears the threshold.

**Daily batch.** `ProcessPayoutBatch` runs on a schedule, computes each eligible vendor's net, and for each calls
**payment-service `POST /payouts`** with a per-payout `Idempotency-Key` and a shared `batch_id`. **No-double-pay
guarantee:** the vendor is marked settled **inside the same transaction** that records the payout, and the
idempotency key means a retried or re-run batch returns the original result instead of paying again — so a crash
mid-batch is safe to re-run (settled vendors are skipped). See §6 and the [decision log](./technical-decision-log.md)
(ADR-09).

### 3.5 Payment-service internal contracts

**Not publicly reachable.** Every endpoint below requires the shared-secret bearer token and an `Idempotency-Key`
header; the service is internal-network only. Amounts are integer poisha. On a duplicate `Idempotency-Key` the
service **replays the stored original result** (same status code + body) and performs **no** new side effect.

```jsonc
// POST /payments   (core-api → payment-service: charge for an order)
// Headers: Authorization: Bearer [PLACEHOLDER]; Idempotency-Key: <per charge attempt>
{ "order_id": 99, "amount": 10000, "currency": "BDT", "gateway": "stripe_sim",
  "callback_url": "https://core-api/internal/payments/webhook" }
// 201 → { "success": true, "data": { "payment": { "ref": "[PLACEHOLDER]", "status": "pending" } } }
// The real result (succeeded/failed) is delivered asynchronously via the signed webhook below.

// POST /refunds    (full or partial; validated against the original charge)
{ "payment_ref": "[PLACEHOLDER]", "amount": 5000, "currency": "BDT" }
// 200 → { "success": true, "data": { "refund": { "ref": "[PLACEHOLDER]", "status": "completed" } } }

// POST /payouts    (per-vendor settlement from a batch)
{ "vendor_id": 7, "amount": 900000, "currency": "BDT", "batch_id": "2026-06-26" }
// 200 → { "success": true, "data": { "payout": { "ref": "[PLACEHOLDER]", "status": "paid" } } }
```

**Webhook callback (payment-service → core-api).** Reports the terminal charge/refund/payout result.
- Headers: `Authorization: Bearer [PLACEHOLDER]` **and** `X-Signature: <hex HMAC-SHA256(raw_body, shared_secret)>`.
- core-api recomputes the HMAC over the raw body and rejects on mismatch (replay/tamper-safe), then processes the
  result **idempotently** (keyed on the payment ref), tolerating duplicate and out-of-order deliveries.

```jsonc
{ "event": "payment.succeeded", "payment_ref": "[PLACEHOLDER]", "order_id": 99,
  "status": "succeeded", "amount": 10000, "currency": "BDT", "occurred_at": "2026-06-27T12:03:00Z" }
```

## 4. Database design
Full ERD + relationship explanations in [`erd.md`](./erd.md). Summarise here:

### Key relationships
`users` 1:1 `vendors` / `attendees` (role profile); `vendor` 1:N `events` 1:N `ticket_types`; `attendee` 1:N
`orders` 1:N `order_items`. **`order` 1:N `payments`** (each charge attempt is its own row, ≤1 `succeeded` — see
[erd.md](./erd.md) retry-cardinality note) and `payment` 1:N `refunds` (partials). `ticket_type` 1:N `ticket_holds`
(transient) and 1:N `tickets` (issued on payment). `vendor` 1:N `payouts`; `order` 1:N `disputes`; vendor 1:N
`kyc_documents`. `ledger_entries` is polymorphic + append-only; `idempotency_keys` guards money calls.

### Normalization / denormalization decisions
Normalized to ~3NF; four **deliberate** denormalizations, each for a hot path or an immutability guarantee:
`ticket_types.quantity_sold` (a counter, so the availability check under the row lock avoids a `COUNT`);
`order_items.unit_price` and `orders.commission_rate` (snapshots — price quoted = price charged, payouts use the
historical rate); `ledger_entries.vendor_id` (attribution, so vendor balance is one indexed aggregate, not a 3-table
join); and `sales_reports` (a cached daily rollup for dashboards, recomputable from the ledger). Full rationale in
[erd.md](./erd.md).

### Indexing strategy
Highlights (the full named-index table is in [erd.md](./erd.md)):
- `events(status, starts_at)` — public listing + the reminder-window cron.
- `ticket_holds(status, expires_at)` — the `ReleaseExpiredHolds` scan; `ticket_holds(ticket_type_id, status)` — the
  active-holds sum during the availability check.
- `orders(idempotency_key)` UNIQUE — idempotent re-checkout; `orders(attendee_id)` — order history.
- `payments(idempotency_key)` UNIQUE — de-dupe a retried charge; `payouts(idempotency_key)` UNIQUE — no double-pay.
- `ledger_entries(vendor_id, created_at)` — balance aggregate; `ledger_entries(subject_type, subject_id)` — tracing.

### Financial audit trail
`ledger_entries` is the **append-only, polymorphic** source of truth: every money state change (sale, commission,
refund, payout, clawback) is a new **signed** row — never an update or delete. Vendor balance and platform commission
are **computed by aggregation** over the ledger (no mutable balance column), so the books always reconcile and a
refund-after-payout is just a negative `clawback` entry. The payment-service keeps its own transactions +
idempotency records in its own DB. Financial history is never overwritten.

### Soft-delete vs hard-delete strategy
Three tiers (detailed in [erd.md](./erd.md)): **soft-delete** (`deleted_at`) reference data that historical orders
still point at — `users`, `vendors`, `attendees`, `events`, `ticket_types`, `kyc_documents` — so it stays resolvable
for audit; **never delete** the financial/issued records — `orders`, `order_items`, `payments`, `refunds`, `payouts`,
`ledger_entries`, `tickets`, `disputes`, `event_reminders` — lifecycle is a `status` column, regulatory retention
forbids deletion; **transient/config** — `ticket_holds` resolve to released/converted, `idempotency_keys` are pruned
after their window, `settings`/`sales_reports` are updated/upserted in place.

## 5. Background job design
For each: trigger, what it does, failure behaviour, duplicate-processing prevention.

| Job | Trigger | On failure | No-duplicate guarantee |
|---|---|---|---|
| ProcessPayoutBatch | daily schedule | partial batch safe to re-run | mark vendor processed **inside** the payout txn + idempotency key/batch_id |
| SendEventReminders | hourly | re-queue failed sends | one `event_reminders` row per `(event_id, type)` — insert-or-skip |
| ReleaseExpiredHolds | every 5 min | idempotent re-scan | release only `active` holds past `expires_at`, in a txn |
| GenerateSalesReport | daily | regenerate is idempotent | upsert per `(report_date, vendor_id)` (+ app dedupe for the NULL platform-wide row) |
| ProcessWaitlist | on ticket release | retry | offer/lock per waitlist position; 30-min `claim_expires_at` |

**Mechanisms.**
- **ProcessPayoutBatch (daily).** Computes each eligible vendor's net from the ledger; for vendors clearing the
  5,000 BDT threshold, calls payment-service `POST /payouts` with a per-payout idempotency key and a shared
  `batch_id`. Marking the vendor settled and recording the payout happen in the **same transaction**, so a mid-batch
  crash is safe to re-run — settled vendors are skipped and the idempotency key blocks any double-pay.
- **SendEventReminders (hourly).** Finds events whose `starts_at` enters the 24h (or 1h) window, and for each
  inserts an `event_reminders(event_id, type)` row **if absent**, then enqueues per-holder notification jobs. The
  unique `(event_id, type)` row makes the dispatch idempotent — a re-run never double-sends. Dedupe is per
  event-window, not per recipient (a deliberate simplification — see [erd.md](./erd.md)).
- **ReleaseExpiredHolds (every 5 min).** The inventory safety net. In a transaction, flips `active` holds with
  `expires_at < now` to `released`, returning their count to availability. Idempotent: only `active` holds are
  touched, so re-scanning does nothing once released. This is what unblocks inventory if a payment never completes.
- **GenerateSalesReport (daily).** Aggregates the previous day's ledger into `sales_reports`, upserting on
  `(report_date, vendor_id)`. Because MySQL treats `NULL` as distinct, the platform-wide (`vendor_id IS NULL`) row is
  deduped at the app layer via `updateOrCreate` (see [erd.md](./erd.md)). Purely derived — recomputable from the ledger.
- **ProcessWaitlist (on ticket release).** When inventory frees up, offers it to the next `waiting` entry for that
  ticket type (by `position`), stamping `offered_at` and `claim_expires_at = +30 min`. A sweep expires unclaimed
  offers and rolls to the next position. Nice-to-have; first to be cut under time pressure.

## 6. Partial-failure & resilience scenarios

The design assumes every cross-service hop can fail, duplicate, or arrive late. The invariant: **inventory and money
are never corrupted, only delayed.**

- **payment-service down or slow.** Checkout still succeeds locally — it creates the `pending` order + holds inside
  the DB transaction, then enqueues the charge as a **queued job** with retry + exponential backoff. If
  payment-service is unreachable, the job retries; the order stays `pending` and the **15-min hold expiry**
  guarantees the reserved inventory is returned if payment never completes. Because the charge carries an
  `Idempotency-Key`, a retry that actually reached the gateway never charges twice. The buyer is never blocked
  synchronously on a slow gateway.
- **Webhook lost.** payment-service **persists the charge result before** attempting the callback and retries
  delivery with backoff; if the callback is permanently lost, the order simply expires via the hold safety net and a
  reconciliation poll can re-fetch the charge status. No money moves without a corresponding order state.
- **Webhook duplicated / out-of-order.** The core-api handler is **idempotent** (keyed on the payment ref) and
  verifies the `X-Signature` HMAC before acting, so a replayed or doubled callback is recognized and produces the
  original result with **no** second ticket issuance or ledger entry. A late "succeeded" arriving after the order
  already expired is handled explicitly (re-secure inventory if still available, else auto-refund — see
  [requirement-analysis.md](./requirement-analysis.md) §5).
- **Notification queue backed up.** Notifications are **never on the checkout path** — they're enqueued to Redis and
  consumed by notification-service. A backlog or a flaky vendor endpoint degrades *delivery latency* only; purchasing
  is unaffected. Failed deliveries retry with backoff and land in a **DLQ** after the max attempts for inspection.
- **Payout batch crash mid-run.** Each vendor is marked settled **inside the same transaction** that records its
  payout, and each payout call carries an idempotency key + `batch_id`. Re-running the batch after a crash skips
  already-settled vendors and the idempotency key blocks any duplicate gateway payout — **no double-pay**.
- **Redis unavailable.** A direct benefit of choosing a **DB row lock** over a Redis lock (ADR-07): oversell
  prevention does **not** depend on Redis at all — the `SELECT … FOR UPDATE` lives in the primary DB transaction, and
  **idempotency is DB-backed too** (the `idempotency_keys` table in core-api + the payment-service's own DB, not a
  Redis cache). So the two money-correctness guarantees — the inventory lock and idempotent money calls — survive a
  Redis outage intact. What Redis carries is the **queue**, and the payment-charge dispatch is itself a queued job, so
  with Redis down **all queued work pauses — both the charge dispatch and notifications**. Nothing corrupts: a
  checkout still commits its `pending` order + holds in the DB, the charge job simply waits, and if Redis stays down
  past 15 minutes the **hold-expiry returns the inventory**. When Redis recovers, queued work resumes and idempotency
  keys ensure the now-delayed charges run exactly once.
