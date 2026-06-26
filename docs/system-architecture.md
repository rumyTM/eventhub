# EventHub — System Architecture

> **Graded deliverable (Rubric 2: System Architecture & Design — 25%).**
> "Excellent" = clean justified service boundaries; documented inter-service auth, error handling, retry; schema with
> financial audit trails, deliberate soft-deletes, explained indexing; graceful partial-failure handling. Fill the
> `<!-- FILL -->` blocks; the diagrams and contract skeletons below are pre-seeded to match the code conventions —
> keep them in sync as the build evolves.

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
   Infra:  MySQL :3306 (db-per-service)   ·   Redis :6379 (queues + locks)
```

### Service boundaries & justification
<!-- FILL: why three services + a frontend, not a monolith. core-api owns the domain & DB of record; payment-service
isolates money/gateway concerns (swappable, independently scalable, blast-radius containment); notification-service
isolates slow/unreliable outbound I/O behind a queue so it never blocks checkout. Justify each split — the rubric
penalizes "monolith crammed into microservices without clear boundaries". -->

### Communication & data-flow matrix
| From → To | Protocol | Auth | Failure handling |
|---|---|---|---|
| frontend → core-api | REST `/api/v1` | Sanctum bearer (user) | Envelope `errors`/`message`; 401→login, 403, 429+retry_after |
| core-api → payment-service | REST | Shared secret + `Idempotency-Key` | Retry w/ backoff in a queued job; order stays `pending`; idempotent |
| payment-service → core-api | REST webhook | Shared secret + HMAC signature | Result persisted first; delivery retried; webhook handler idempotent |
| core-api → notification-service | Redis queue | Trusted network + queue name | Durable jobs; backlog never blocks checkout |
| notification-service → vendor | REST webhook | HMAC signature | Exponential backoff, max 5, dead-letter |

## 2. Authentication & authorization strategy
<!-- FILL:
- User auth: Sanctum personal access tokens; roles admin/vendor/attendee via a role enum + EnsureRole middleware.
- Ownership: vendor scoped to own events/orders/payouts (policy/middleware on vendor_id); attendee blocked from
  admin/vendor routes.
- Inter-service: static shared-secret bearer per service (env); payment & notification endpoints never public.
- Webhook integrity: HMAC-SHA256 of body with the shared secret, verified alongside the bearer token (replay-safe).
- Secrets in env only; [PLACEHOLDER] in docs/fixtures. -->

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
<!-- FILL: validation rules, status defaults, ownership, partial-failure on ticket_types. -->

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
<!-- FILL: the lock-and-decrement sequence, idempotent re-checkout returning the same order, what the client polls /
how the webhook flips it to paid. -->

### 3.3 Process refund — `POST /api/v1/orders/{order}/refund` (attendee request / admin approve)
```jsonc
// Request
{ "amount": 5000, "reason": "..." }   // amount optional for full refund
// Response 200
{ "success": true, "message": "Refund processed", "errors": null,
  "data": { "refund": { "id": 5, "amount": 5000, "policy_applied": "50%_24_48h",
            "status": {"value":"completed"} } } }
```
<!-- FILL: policy resolution (100% >48h, 50% 24–48h, 0% <24h vs starts_at), full vs partial, dispute mediation,
call to payment-service, ledger write. -->

### 3.4 Calculate payout — `GET /api/v1/vendors/{vendor}/payouts/preview` (vendor/admin) + batch
```jsonc
// Response 200
{ "success": true, "message": "OK", "errors": null,
  "data": { "gross": 100000, "commission_rate": 0.10, "commission": 10000, "net": 90000,
            "currency": "BDT", "meets_threshold": true, "threshold": 50000 } }
```
<!-- FILL: formula, per-vendor commission override, threshold rollover, how the daily batch queues to payment-service
and reports back, idempotency + batch_id, no-double-pay guarantee. -->

### 3.5 Payment-service internal contracts
<!-- FILL: POST /payments, POST /refunds, POST /payouts (request/response + Idempotency-Key behaviour), and the
webhook callback body + X-Signature header. Mark "not publicly reachable". -->

## 4. Database design
Full ERD + relationship explanations in [`erd.md`](./erd.md). Summarise here:

### Key relationships
<!-- FILL: users 1:1 vendors/attendees; vendor 1:N events; event 1:N ticket_types; order 1:N order_items;
order 1:1 payment 1:N refunds; ticket_type 1:N ticket_holds & tickets; vendor 1:N payouts; order 1:N disputes;
append-only ledger_entries; idempotency_keys. -->

### Normalization / denormalization decisions
<!-- FILL: normalized core; justified denormalizations (e.g. quantity_sold counter on ticket_types for fast
availability; cached daily sales_reports for dashboards). Explain WHY each. -->

### Indexing strategy
<!-- FILL: name the indexes and the query they serve, e.g.
- events(status, starts_at) — listing/discovery + reminder lookup.
- ticket_types(event_id), unique business keys.
- ticket_holds(expires_at) — expiry cron scan.
- orders(attendee_id, status), orders(idempotency_key UNIQUE).
- payouts(vendor_id, status), idempotency_keys(key UNIQUE).
- ledger_entries(subject_type, subject_id, created_at). -->

### Financial audit trail
<!-- FILL: append-only ledger_entries for every order/payment/refund/payout state change; payment-service
transactions table; never overwrite financial history. -->

### Soft-delete vs hard-delete strategy
<!-- FILL: soft-delete events, vendors, attendees (recoverable, referenced by orders); NEVER hard-delete financial
records (orders/payments/refunds/payouts/ledger — regulatory retention); hard-delete only transient rows like expired
holds (or soft + prune). Justify each. -->

## 5. Background job design
For each: trigger, what it does, failure behaviour, duplicate-processing prevention.

| Job | Trigger | On failure | No-duplicate guarantee |
|---|---|---|---|
| ProcessPayoutBatch | daily schedule | partial batch safe to re-run | mark vendor processed **inside** the payout txn + idempotency key/batch_id |
| SendEventReminders | hourly | re-queue failed sends | `reminded_at` flag per ticket-holder |
| ReleaseExpiredHolds | every 5 min | idempotent re-scan | release only `active` holds past `expires_at`, in a txn |
| GenerateSalesReport | daily | regenerate is idempotent | upsert per (date, vendor) |
| ProcessWaitlist | on ticket release | retry | offer/lock per waitlist position |
<!-- FILL: expand each with the actual mechanism. -->

## 6. Partial-failure & resilience scenarios
<!-- FILL (rubric explicitly rewards this): payment-service down; webhook lost/duplicated; notification queue backed
up; payout batch crash mid-run; Redis unavailable for locks (fallback to DB lock?). State the behaviour and why the
system stays correct. -->
