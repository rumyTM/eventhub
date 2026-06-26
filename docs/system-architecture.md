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
   Infra:  MySQL :3306 (db-per-service; authoritative inventory lock = DB row lock)   ·   Redis :6379 (queues + per-ticket_type lock that fronts it)
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
  and the *would-be* PCI-scope surface if real gateways were integrated) and must contain blast radius: it is the only
  service that touches gateway credentials and charge flows, so keeping it separate means a gateway integration bug or
  a credential-handling concern never sits in the same process as the order DB. **Scope note:** because gateways here
  are **simulated and no raw cardholder data is ever stored**, the whole platform stays **out of PCI scope** today;
  isolating this service is what would *keep PCI scope contained to it alone* if a real gateway were later added. (PCI
  applies only to cardholder data — not to EventHub's KYC/PII, which is governed separately; see ADR-16.) It is
  **stateless about EventHub's domain** — it knows charges, refunds, payouts, and idempotency keys, not "events" or
  "vendors" beyond the IDs core-api passes. This makes gateways swappable behind `PaymentGatewayContract` and lets the
  money path scale and be hardened independently.
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
| notification-service → vendor | REST webhook | HMAC signature | Exponential backoff 1/4/16/64/256s, max 5 retries (6 total attempts), then dead-letter |

## 2. Authentication & authorization strategy

**User auth (frontend → core-api).** Laravel Sanctum personal-access tokens carried as `Authorization: Bearer`,
persisted in Sanctum's framework-provided `personal_access_tokens` table (hashed; not modelled in the ERD as it's
framework infrastructure). Every user has a `role` enum (`admin | vendor | attendee`); an `EnsureRole` middleware
gates route groups by role, and `php artisan`-style **policies** enforce the finer-grained checks. Tokens are issued
at login and revoked at logout; no session cookies for the API.

**Authorization is role + ownership, in two layers.** Role decides *which routes* you may call; ownership decides
*which rows*. A vendor may only read/mutate events, ticket types, orders, and payouts reachable through its own
`vendor_id` (enforced in policies, never in controllers); an attendee may only see/refund its **own** orders and is
blocked from all admin/vendor routes; admin-only actions (KYC decisions, commission settings, dispute resolution)
sit behind the admin role. Ownership checks are mandatory even on "obvious" routes so an authenticated vendor can
never reach another vendor's data by guessing IDs. As defence-in-depth, **all primary keys are ULIDs** (ADR-19), so
IDs are non-enumerable across tenants — an attacker can't walk sequential integers to probe another vendor's orders
even before the ownership policy rejects them. Roles are a backed enum (not spatie/laravel-permission) — three fixed
roles, one per user; row-level ownership is enforced by policies/scoping on `vendor_id` regardless of the role
mechanism (see ADR-21).

**Inter-service auth (server-to-server).** Every cross-service HTTP call carries a **static shared-secret bearer
token**, defined per service in `.env` (`PAYMENT_SERVICE_TOKEN`, etc.). Payment-service and notification-service
endpoints are **never publicly reachable** — they sit on the internal network and reject any request without the
shared secret. core-api → notification-service goes over the **Redis queue** (trusted network + queue name), not HTTP.

**Webhook integrity (replay-safe).** The payment-service → core-api callback is verified by **two** factors: the
shared-secret bearer token *and* an `X-Signature` header that is an **HMAC-SHA256** keyed by the shared secret. To
defeat replay (not just tampering), the signature covers a **timestamp + nonce** alongside the raw body —
`HMAC(timestamp ‖ nonce ‖ body)`, sent as `X-Timestamp` / `X-Nonce` headers. The handler recomputes the HMAC, rejects
on mismatch, rejects a stale timestamp (outside a small skew window), and rejects a nonce it has already seen — so a
captured-and-replayed request fails even though its signature is valid. Vendor-facing webhooks from
notification-service are signed the same way, keyed by each vendor's own `webhook_secret`.

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
// Response 201  (ids are ULIDs — ADR-19)
{ "success": true, "message": "Event created", "errors": null,
  "data": { "event": { "id": "01HF8Z9Q7K3M2N4P5R6S7T8V9W", "status": {"value":"draft","label":"Draft"},
            "timezone": "Asia/Dhaka", "capacity": 500, "...": "..." } } }
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
{ "items": [ { "ticket_type_id": "01HF8ZB0A1C2D3E4F5G6H7J8K9", "quantity": 2 } ] }
// Response 201 — hold created, payment pending  (ids are ULIDs — ADR-19)
{ "success": true, "message": "Checkout started", "errors": null,
  "data": { "order": { "id": "01HF8ZC1B2D3E4F5G6H7J8K9M0", "status": {"value":"pending","label":"Pending"},
            "currency": "BDT", "total": 10000, "hold_expires_at": "2026-06-27T12:15:00Z" },
            "payment": { "ref": "[PLACEHOLDER]", "status": {"value":"pending","label":"Pending"} } } }
// 409 if inventory insufficient (TicketsUnavailableException)
```
**Lock-and-decrement sequence (hybrid lock).** Oversell is prevented with a **hybrid lock** (see ADR-07): a
short-lived **Redis lock per `ticket_type`** *fronts* an authoritative **DB row lock** (`SELECT … FOR UPDATE`) taken
*inside* the checkout transaction.

```
for each line item (ordered by ticket_type_id to avoid deadlocks):
  acquire Redis lock "lock:ticket_type:{id}"  (short TTL)        -- distributed gate: thins contention before the DB
  BEGIN
    SELECT * FROM ticket_types WHERE id = ? FOR UPDATE           -- AUTHORITATIVE serialization of concurrent buyers
    available = quantity_total - quantity_sold
              - SUM(holds WHERE status='active' AND expires_at > now())   -- only NON-EXPIRED holds count
    if available < requested:  ROLLBACK → 409 TicketsUnavailable
    create order (status=pending) + one ticket_hold per item (expires_at = now+15m), unit_price locked here
  COMMIT                                                         -- DB row lock releases on commit
  release Redis lock
```

**Why both:** the Redis lock satisfies the brief's **"distributed"** requirement — it serializes contending
checkouts across multiple core-api workers *before* they hit the database, **cutting `FOR UPDATE` contention** on a
hot ticket type. But the **DB row lock is authoritative**: it is what actually guarantees no oversell, so correctness
holds even if Redis is unavailable (we fall back to DB-only locking and just lose the contention optimization — see
§6). `quantity_sold` is **not** decremented here; the hold is what reserves inventory until payment.

**Expiry is enforced at read time.** Availability counts only holds that are both `active` **and** `expires_at >
now()`, so a hold **never blocks stock past its 15-minute life** even if cleanup hasn't run yet. The
`ReleaseExpiredHolds` cron (every 5 min, §5) is just **housekeeping** — it flips stale rows to `released` to keep the
table tidy and waitlist logic simple — not the thing that frees inventory. Correctness doesn't depend on the cron's
cadence.

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
// Request  — NO amount: the attendee selects WHICH tickets, not how much.
//   omit "items" = refund the whole order; include items = partial (a subset of tickets)
{ "items": [ { "order_item_id": "01HF8ZB0A1C2D3E4F5G6H7J8K9", "quantity": 1 } ], "reason": "..." }
// Response 200  (ids are ULIDs — ADR-19; amount is computed by core-api, not supplied)
{ "success": true, "message": "Refund processed", "errors": null,
  "data": { "refund": { "id": "01HF8ZD2C3E4F5G6H7J8K9M0N1", "amount": 5000, "policy_applied": "50%_24_48h",
            "status": {"value":"completed","label":"Completed"} } } }
```
**Policy resolution.** The refund percentage is resolved against the event's `starts_at` **UTC instant**: **>48h →
100%, 24–48h → 50%, <24h → 0%**. **Hybrid model:** an in-policy refund is **auto-approved and executed**; an
out-of-policy request (e.g. buyer contests the 0% window) does **not** refund — it opens a `dispute` for an admin to
mediate, who may then resolve it by issuing a refund (`disputes.refund_id`) or reject it.

**The attendee never sends an amount.** The request says *which* tickets to refund (omit `items` = the whole order;
include `items` = a **partial** refund of that subset), not how much. **core-api derives the amount**:
`amount = policy% × (selected line totals)`, where `policy%` is resolved from time-to-event (100% >48h, 50% 24–48h,
0% <24h). This keeps the money math server-side and untamperable — a client can't request an arbitrary refund value.
The computed amount is validated against the **ledger** so cumulative refunded can never exceed the original charge,
and a ticket already `checked_in` is not refundable.

**Compute vs execute split.** core-api **decides** policy and computes the amount, then calls **payment-service
`POST /refunds`** (§3.5) with that exact amount + a shared secret + `Idempotency-Key`. The payment-service is a pure
**executor** — it moves the money for the amount it's given and never computes policy. On success core-api writes a
signed `refund` ledger entry (and flips the order to `refunded` or `partially_refunded`). **Interaction with
settlement (ADR-20):** vendor revenue settles only **after the event is `completed`**, so a refund before then simply
reduces still-unsettled revenue (held as `reserved_refund`) — no clawback needed. **Refund-after-payout** is the rare
fallback: if the revenue was already settled (a post-event dispute override), the refund also writes a negative
`clawback` ledger entry that offsets the vendor's next payout (balance may go negative — see [erd.md](./erd.md)).

**Vendor-cancelled event (ADR-23).** A vendor/admin cancellation refunds **every attendee 100%** (policy-overridden),
funded by **debiting the vendor** (a negative `clawback` ledger entry), and the **platform refunds its own
commission** too — it earns nothing on an event that never happened, and the sale + reversal net to zero. Because
settlement waits for event completion, in the common case the vendor hasn't been paid yet, so the debit nets before
any real money left the platform.

**Two distinct endpoints.** This route is the **attendee's** refund *request* (auto-executes only when in-policy).
Resolving an out-of-policy **dispute** is a **separate admin endpoint** (e.g. `POST
/api/v1/admin/disputes/{dispute}/resolve`) — admin-only, operates on the `dispute`, and may issue the refund
(setting `disputes.refund_id`) or reject it. Keeping them separate cleanly splits the attendee and admin authorization
surfaces.

### 3.4 Calculate payout — `GET /api/v1/vendors/{vendor}/payouts/preview` (vendor/admin) + batch
```jsonc
// Response 200  (amounts in poisha; 500000 poisha = 5,000 BDT threshold)
// gross counts only orders whose event is COMPLETED (ADR-20); reserved_refund is held against not-yet-settled orders
{ "success": true, "message": "OK", "errors": null,
  "data": { "gross": 1000000, "commission_rate": 0.10, "commission": 100000, "net": 900000,
            "reserved_refund": 150000, "currency": "BDT", "meets_threshold": true, "threshold": 500000 } }
```

**Formula.** `net = gross − commission`, where `gross` = sum of paid, non-refunded order revenue attributable to the
vendor (from the **ledger**, not a mutable balance) **and only for orders whose event is `completed`** (ADR-20) —
revenue from events that haven't happened yet is excluded and tracked as `reserved_refund` until the event completes,
so a cancelled/no-show event is never paid out in the first place. `commission = round_half_up(gross ×
commission_rate)`.
**Commission rate is snapshotted per order at sale time** (`orders.commission_rate`), so a payout sums each order's
own historical rate — changing the platform rate later never rewrites past payouts. A per-vendor override on
`vendors.commission_rate` takes precedence over the platform default *at sale time* (it's what gets snapshotted).

**Threshold rollover.** If the vendor's eligible `net` is below the **5,000 BDT (500,000 poisha)** minimum, no payout
is created — the balance simply remains in the ledger and **rolls into the next cycle** until it clears the threshold.

**Daily batch.** `ProcessPayoutBatch` runs on a schedule, computes each eligible vendor's net over **orders whose
event is `completed` only**, and for each calls **payment-service `POST /payouts`** with a per-payout
`Idempotency-Key` and a shared `batch_id`. Each settled order is recorded in **`payout_items`** (`payout_id`,
`order_id`, `settled_amount`) so every payout is **traceable to the exact orders it paid for**, and the payout carries
its `reserved_refund`. **No-double-pay guarantee:** the vendor is marked settled (and `payout_items` written)
**inside the same transaction** that records the payout, and the idempotency key means a retried or re-run batch
returns the original result instead of paying again — so a crash mid-batch is safe to re-run (settled vendors are
skipped). Settling only after event completion means clawbacks are a rare fallback rather than routine. See §6 and the
[decision log](./technical-decision-log.md) (ADR-09, ADR-20, ADR-23).

### 3.5 Payment-service internal contracts

**Not publicly reachable.** Every endpoint below requires the shared-secret bearer token and an `Idempotency-Key`
header; the service is internal-network only. Amounts are integer poisha. On a duplicate `Idempotency-Key` the
service **replays the stored original result** (same status code + body) and performs **no** new side effect.

```jsonc
// POST /payments   (core-api → payment-service: charge for an order)
// Headers: Authorization: Bearer [PLACEHOLDER]; Idempotency-Key: <per charge attempt>
{ "order_id": "01HF8ZC1B2D3E4F5G6H7J8K9M0", "amount": 10000, "currency": "BDT", "gateway": "stripe_sim",
  "callback_url": "https://core-api/internal/payments/webhook" }
// 201 → { "success": true, "data": { "payment": { "ref": "[PLACEHOLDER]", "status": {"value":"pending","label":"Pending"} } } }
// The real result (succeeded/failed) is delivered asynchronously via the signed webhook below.

// POST /refunds    (executes the amount core-api computed; validated against the original charge)
{ "payment_ref": "[PLACEHOLDER]", "amount": 5000, "currency": "BDT" }
// 200 → { "success": true, "data": { "refund": { "ref": "[PLACEHOLDER]", "status": {"value":"completed","label":"Completed"} } } }

// POST /payouts    (per-vendor settlement from a batch)
{ "vendor_id": "01HF8ZE3D4F5G6H7J8K9M0N1P2", "amount": 900000, "currency": "BDT", "batch_id": "2026-06-26" }
// 200 → { "success": true, "data": { "payout": { "ref": "[PLACEHOLDER]", "status": {"value":"paid","label":"Paid"} } } }
```

**Webhook callback (payment-service → core-api).** Reports the terminal charge/refund/payout result.
- Headers: `Authorization: Bearer [PLACEHOLDER]` **and** `X-Signature: <hex HMAC-SHA256(raw_body, shared_secret)>`.
- core-api recomputes the HMAC over the raw body and rejects on mismatch (replay/tamper-safe), then processes the
  result **idempotently** (keyed on the payment ref), tolerating duplicate and out-of-order deliveries.

```jsonc
{ "event": "payment.succeeded", "payment_ref": "[PLACEHOLDER]", "order_id": "01HF8ZC1B2D3E4F5G6H7J8K9M0",
  "status": {"value":"succeeded","label":"Succeeded"}, "amount": 10000, "currency": "BDT",
  "occurred_at": "2026-06-27T12:03:00Z" }
```

## 4. Database design
Full ERD + relationship explanations in [`erd.md`](./erd.md). Summarise here:

### Key relationships
**All primary keys are ULIDs** (`HasUlids`, `foreignUlid` FKs — ADR-19): non-enumerable across tenants and
time-sortable for index locality.

`users` 1:1 `vendors` / `attendees` (role profile); `vendor` 1:N `events` 1:N `ticket_types`; `attendee` 1:N
`orders` 1:N `order_items`. **`order` 1:N `payments`** (each charge attempt is its own row, ≤1 `succeeded` — see
[erd.md](./erd.md) retry-cardinality note) and `payment` 1:N `refunds` (partials). `ticket_type` 1:N `ticket_holds`
(transient); **`tickets` hang off `order_items`** (a group bundle line issues N tickets, each with its own
`valid|checked_in|transferred|refunded` status). `vendor` 1:N `payouts` 1:N **`payout_items`** (linking a payout to
the exact orders it settled); `order` 1:N `disputes`; vendor 1:N `kyc_documents`. `events.capacity` is a hard ceiling
(`SUM(ticket_types.quantity_total) ≤ capacity`), and an order can be `partially_refunded`. `ledger_entries` is
polymorphic + append-only; `idempotency_keys` guards money calls.

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
`payout_items`, `ledger_entries`, `tickets`, `disputes`, `event_reminders` — lifecycle is a `status` column (e.g.
`orders.status` can be `partially_refunded`), regulatory retention forbids deletion; **transient/config** —
`ticket_holds` resolve to released/converted, `idempotency_keys` are pruned after their window, `settings`/
`sales_reports` are updated/upserted in place.

## 5. Background job design
For each: trigger, what it does, failure behaviour, duplicate-processing prevention.

| Job | Trigger | On failure | No-duplicate guarantee |
|---|---|---|---|
| ProcessPayoutBatch | daily schedule | partial batch safe to re-run | mark vendor processed **inside** the payout txn + idempotency key/batch_id |
| SendEventReminders | hourly | re-queue failed sends | one `event_reminders` row per `(event_id, type)` — insert-or-skip |
| ReleaseExpiredHolds | every 5 min | idempotent re-scan | release only `active` holds past `expires_at`, in a txn |
| GenerateSalesReport | daily | regenerate is idempotent | upsert per `(report_date, vendor_id)` (+ app dedupe for the NULL platform-wide row) |
| ProcessWaitlist | on ticket release | retry | offer/lock per waitlist position; 30-min `claim_expires_at` |
| TransitionEventStatus | every few min | idempotent re-scan | derive `published→ongoing→completed` from `starts_at`/`ends_at`; only advance from the expected current status |

**Mechanisms.**
- **ProcessPayoutBatch (daily).** Computes each eligible vendor's net from the ledger **over orders whose event is
  `completed` only (ADR-20)** — and holds back not-yet-settled revenue as `reserved_refund`. For vendors clearing the
  5,000 BDT threshold, calls payment-service `POST /payouts` with a per-payout idempotency key and a shared
  `batch_id`, writing a `payout_items` row per settled order. Marking the vendor settled, writing `payout_items`, and
  recording the payout happen in the **same transaction**, so a mid-batch crash is safe to re-run — settled vendors
  are skipped and the idempotency key blocks any double-pay. Settling only after event completion keeps clawbacks a
  rare fallback (and a cancelled event is never paid out — ADR-23).
- **TransitionEventStatus (every few min).** Drives the event lifecycle off the clock: an event with
  `status = published` whose `starts_at` has passed becomes `ongoing`; one whose `ends_at` has passed becomes
  `completed` (which is what makes its revenue settle-eligible, ADR-20). `cancelled` is **never** automatic — it's a
  manual vendor/admin action. Idempotent: the command only advances an event from its expected current status (a
  re-scan re-selecting an already-`completed` event does nothing), so repeated runs converge rather than thrash.
- **Event cancellation (manual, event-driven — not cron).** A vendor/admin cancellation enqueues a **100% refund for
  every attendee** (policy-overridden), each **debiting the vendor** via a negative `clawback` ledger entry with the
  **platform also refunding its commission** (ADR-23), plus cancellation notifications. Mass refunds run as queued
  jobs (idempotent per order) so a large event doesn't block the request; the ledger stays balanced as sale + full
  reversal net to zero.
- **SendEventReminders (hourly).** Finds events whose `starts_at` enters the 24h (or 1h) window, and for each
  inserts an `event_reminders(event_id, type)` row **if absent**, then enqueues per-holder notification jobs. The
  unique `(event_id, type)` row makes the dispatch idempotent — a re-run never double-sends. **Hourly granularity**
  means a reminder fires within **±1 hour** of the exact 24h-before mark; that imprecision is fine for a courtesy
  reminder, and the `event_reminders` row guarantees it's sent **once only**. Dedupe is per event-window, not per
  recipient (a deliberate simplification — see [erd.md](./erd.md)).
- **ReleaseExpiredHolds (every 5 min).** The inventory safety net. In a transaction, flips `active` holds with
  `expires_at < now` to `released`, returning their count to availability. Idempotent: only `active` holds are
  touched, so re-scanning does nothing once released. This is what unblocks inventory if a payment never completes.
- **GenerateSalesReport (daily).** Aggregates **ledger entries by date** into `sales_reports`, upserting on
  `(report_date, vendor_id)`. Because each report is an aggregate of that day's ledger rows, **a later refund reduces
  the net on its *own* date and never rewrites a past day's report** — history is immutable, corrections land on the
  day they happen (consistent with the append-only ledger). A period total is just the **sum of the ledger over the
  range**, which therefore reconciles exactly with the per-day reports. Because MySQL treats `NULL` as distinct, the
  platform-wide (`vendor_id IS NULL`) row is deduped at the app layer via `updateOrCreate` (see [erd.md](./erd.md)).
  Purely derived — recomputable from the ledger.
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
  is unaffected. Failed deliveries retry on exponential backoff (1/4/16/64/256s, max 5 retries = 6 total attempts) and
  land in a **DLQ** on exhaustion for inspection/replay (see ADR-18).
- **Payout batch crash mid-run.** Each vendor is marked settled **inside the same transaction** that records its
  payout, and each payout call carries an idempotency key + `batch_id`. Re-running the batch after a crash skips
  already-settled vendors and the idempotency key blocks any duplicate gateway payout — **no double-pay**.
- **Redis unavailable.** Oversell prevention still holds. In the hybrid lock (ADR-07) the Redis lock is only the
  contention-cutting *front*; the **authoritative guard is the DB row lock** (`SELECT … FOR UPDATE`) inside the
  transaction — so with Redis down we **fall back to DB-only locking** and lose the optimization, not the guarantee.
  **Idempotency is DB-backed too** (the `idempotency_keys` table in core-api + the payment-service's own DB, not a
  Redis cache), so both money-correctness guarantees survive a Redis outage. What Redis also carries is the **queue**,
  and the payment-charge dispatch is itself a queued job, so with Redis down **all queued work pauses — both the
  charge dispatch and notifications**, and checkouts serialize a little harder on the DB row lock without the Redis
  front. Nothing corrupts: a checkout still commits its `pending` order + holds in the DB, the charge job simply
  waits, and if Redis stays down past 15 minutes the **hold-expiry returns the inventory** (same safety net as
  before). When Redis recovers, queued work resumes and idempotency keys ensure the now-delayed charges run exactly
  once.
