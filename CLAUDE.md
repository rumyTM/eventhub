# EventHub — Monorepo Engineering Guide (root CLAUDE.md)

> **Read this first.** This file is the entry point for any human or AI agent working in this repository.
> It should make you productive in ~30 minutes: what the system is, how the services fit together, how to run
> everything, where the deeper rules live, and what to do next. Each service has its **own** `CLAUDE.md` with the
> detailed standards for that service — read the one for the service you are touching.

---

## 1. What EventHub is

EventHub is a **multi-vendor event ticketing & payout platform**. Three stakeholder roles:

- **Vendors** (event organizers) — create events, configure ticket types, track sales, receive payouts.
- **Attendees** — browse events, buy tickets, manage orders, check in via QR code.
- **Platform Admins** — approve vendors (KYC), resolve disputes/refunds, set commission, monitor platform health.

**The platform handles money.** Every financial operation (order, payment, refund, payout) must be
**auditable, idempotent, and resilient to partial failure**. That principle outranks feature count — a smaller
system that never double-charges or double-pays beats a feature-complete one that does.

This repository is a take-home assessment deliverable. The graded artifacts (planning docs, AI-workflow files,
code, tests, API docs, seed data) are tracked in [`PLAN.md`](./PLAN.md); progress and decisions are logged in
[`WORKLOG.md`](./WORKLOG.md). **Update `WORKLOG.md` at the end of every working session.**

---

## 2. System map

```
                              ┌─────────────────────────┐
                              │   frontend (Next.js 14)  │   :3000
                              │  vendor / attendee / admin│
                              └───────────┬─────────────┘
                                          │ REST + Bearer (Sanctum user token)
                                          ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                       core-api  (Laravel 11, PHP 8.2+)            :8000     │
│  Events · Ticket types · Orders/holds · Attendees · Vendors/KYC ·          │
│  Payouts · Admin · Auth (Sanctum, roles) · Cron jobs · Sales reports       │
└───────┬───────────────────────────┬────────────────────────┬──────────────┘
        │ REST  (shared-secret +     │ enqueue (Redis)        │ REST callback
        │  Idempotency-Key)          │ notification jobs      │ ◄── webhook
        ▼                            ▼                        │
┌────────────────────────┐   ┌─────────────────────────┐     │
│ payment-service        │   │ notification-service    │     │
│ (Laravel 11)   :8001   │   │ (Node.js)        :8002  │─────┘ (payment webhook
│ StripeSim / PayPalSim  │   │ BullMQ workers          │      hits core-api)
│ charges · refunds ·    │   │ email(sim) · vendor     │
│ payouts · idempotency  │   │ webhooks · retry/DLQ    │
└───────┬────────────────┘   └───────────┬─────────────┘
        │ webhook callback (shared secret)│ outbound HTTP
        └──────────► core-api             └──────────► vendor webhook URLs
                                          
   Shared infra:   MySQL :3306 (one DB per service)   ·   Redis :6379 (queues + inventory lock; DB row lock is authoritative)
```

### Services at a glance

| Service | Stack | Port | DB | Owns | Detailed rules |
|---|---|---|---|---|---|
| `services/core-api` | Laravel 11, PHP 8.2+ | 8000 | `eventhub_core` | Events, tickets, orders, holds, vendors, payouts, admin, auth, cron | [`services/core-api/CLAUDE.md`](./services/core-api/CLAUDE.md) |
| `services/payment-service` | Laravel 11 | 8001 | `eventhub_payments` | Gateway simulation, charges, webhooks, refunds, payout execution, idempotency | [`services/payment-service/CLAUDE.md`](./services/payment-service/CLAUDE.md) |
| `services/notification-service` | Node.js (BullMQ) | 8002 | `eventhub_notifications` | Email (simulated), vendor webhooks, retry/backoff, DLQ, delivery tracking | [`services/notification-service/CLAUDE.md`](./services/notification-service/CLAUDE.md) |
| `frontend` | Next.js 14 + shadcn/ui | 3000 | — | Vendor dashboard, attendee pages, admin panel | [`frontend/CLAUDE.md`](./frontend/CLAUDE.md) |

> **core-api is the orchestrator.** It is the only service that talks to the user-facing DB of record for orders.
> Payment and notification services are deliberately thin and stateless about EventHub's domain — they know about
> charges and messages, not about "events" or "vendors" beyond the IDs core-api passes them.

---

## 3. How the services communicate

| From → To | Protocol | Auth | Notes |
|---|---|---|---|
| frontend → core-api | REST `/api/v1/*` | Sanctum bearer token (user) | Role-based: admin / vendor / attendee |
| core-api → payment-service | REST | **Shared secret** (`Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}`) + `Idempotency-Key` header | No payment endpoint is publicly reachable |
| payment-service → core-api | REST webhook callback | Shared secret + signature | Reports charge success/failure, refund result, payout result |
| core-api → notification-service | **Redis queue** (BullMQ-compatible job payloads) | Trusted network + queue name | core-api publishes jobs; notification-service consumes |
| notification-service → vendor | Outbound REST webhook | HMAC signature header | Vendor-registered URLs; retry w/ backoff |

**Inter-service auth rule:** every cross-service HTTP call carries a shared secret bearer token defined per
service in `.env`. Never expose payment or notification endpoints publicly. Webhook callbacks are verified by a
signature (HMAC of body with the shared secret), not just the bearer token, to survive replay.

**Idempotency rule:** every money-moving call (create charge, refund, payout) carries an `Idempotency-Key`.
The receiving service stores the key→result and returns the original result on a duplicate — it never performs
the side effect twice.

---

## 4. Canonical API response envelope

**Every** core-api and payment-service HTTP response uses one shape, with the **real HTTP status code**:

```json
{ "success": true, "message": "human readable", "data": {}, "errors": null }
```

- `success` — boolean. `data` — payload (tokens, resources, pagination meta, `retry_after`). `errors` — **field-level
  validation failures only** (`{ "field": ["msg"] }`), else `null`.
- A 404 returns HTTP 404, a 429 returns HTTP 429 — never HTTP 200 with an error buried in the body.
- `retry_after` and any non-field metadata go in `data`, never `errors`.
- This is a superset of the assessment's required `{ success, data, message }` (we add `errors` for validation).

The Node notification-service uses the same JSON shape on its few admin/health endpoints for consistency.

---

## 5. Running everything (docker-compose — primary path)

```bash
# from repo root
cp .env.example .env                      # then fill secrets (see .env.example)
docker compose up -d --build              # brings up mysql, redis, all 4 services + workers
docker compose ps                         # verify all healthy

# one-time bootstrap (migrations + seed)
docker compose exec core-api php artisan migrate --seed
docker compose exec payment-service php artisan migrate --seed
docker compose exec notification-service npm run migrate   # if applicable
```

URLs once up: frontend `http://localhost:3000` · core-api `http://localhost:8000/api/v1` ·
payment-service `http://localhost:8001` · notification-service `http://localhost:8002`.

Seed data creates admin / vendor / attendee logins (see `WORKLOG.md` / seeder output for credentials —
**never commit real credentials; use clearly-fake demo values**).

> **Local / Laragon fallback** (no Docker): documented in the root `README.md`. Run MySQL + Redis locally, then in
> each service `composer install && php artisan migrate --seed && php artisan serve --port=<port>` (Laravel) or
> `npm install && npm run dev` (Node/Next). docker-compose is the supported path; local is the fallback.
>
> **PHP version:** Laravel 11's floor is PHP 8.2, but the committed `composer.lock` pulls Symfony 8.x
> (`php >= 8.4.1`), so the local path needs **PHP 8.4.1+** (8.2/8.3 fail `composer install`). The Docker image is
> `php:8.4-cli`, so the docker-compose path needs no local PHP.

---

## 6. Architecture conventions (apply across all services)

- **core-api & payment-service:** strict layering **Controller → Service → Repository → Model**. Business logic in
  services, data access in repositories, no Eloquent queries outside a repository, no business logic in controllers.
  Full rules in each service's `CLAUDE.md`.
- **All routes versioned** under `/api/v1`.
- **Money:** store as integer minor units or `decimal:N` — **never float**. Always record currency. Every financial
  state change is written to an append-only audit/ledger table, never updated in place.
- **Idempotency + locking:** ticket inventory uses a **hybrid lock** to prevent oversell — a short-lived Redis lock
  per `ticket_type` (reduces contention; satisfies the "distributed" requirement across multiple core-api workers)
  **plus** an authoritative DB row lock (`SELECT ... FOR UPDATE`) inside the checkout transaction. The DB row lock is
  the correctness guard, so oversell is impossible even if Redis is unavailable; Redis is an optimization, not the
  source of truth. Money calls use idempotency keys. See `services/core-api/CLAUDE.md` §Orders + ADR-07.
- **Identifiers:** primary keys are **ULIDs** (Laravel `HasUlids`) — non-enumerable across tenants and time-sortable;
  foreign keys use `foreignUlid`.
- **Security & data protection:** we deliberately stay **out of PCI-DSS scope** — no raw card data (PAN/CVV) is ever
  stored or transmitted; the (simulated) gateway holds the card, we keep only tokens/refs. Separately, never put a
  token, OTP, secret, merchant credential, or KYC/PII (NID, TIN, bank account) in code, logs, tests, or responses —
  use `[PLACEHOLDER]`; that's general security + data-privacy, not PCI. Validate every input. No mass assignment
  (`$request->validated()` only). Sensitive logging is redacted. Consider Bangladesh Bank / data-privacy obligations
  for stored customer/vendor data; flag any field that stores more than necessary.
- **Errors:** shaped once per service (Laravel: `bootstrap/app.php` `withExceptions()`). Controllers/services catch
  only expected domain exceptions; everything else bubbles to the global handler and returns a generic 500 — never
  leak SQL, stack traces, or class names to the client.

---

## 7. Available commands & agents (`.claude/`)

**Slash commands** (core-api / payment-service Laravel work):
`/make-endpoint <verb> <path>` · `/crud <Model>` · `/add-enum <Name> [cases]` · `/add-resource <Model>` ·
`/format-and-test`. Workflow commands: `/update-worklog` · `/day-plan <N>` · `/scaffold-service <name>`.

**Agent skills** (auto-trigger by service boundary): `backend-core-api`, `payment-service`,
`notification-service`, `frontend`, plus `laravel-api-endpoint` (end-to-end endpoint workflow).

**Subagents:** `laravel-code-reviewer` (review pending PHP changes against standards),
`laravel-test-writer` (write/run feature + unit tests), `financial-logic-reviewer` (audit money paths for
idempotency, locking, audit-trail, oversell, double-pay).

**Laravel Boost** (dev-only) is installed in `core-api` and `payment-service` — use its MCP tools (`search-docs` for
version-accurate Laravel docs, plus DB-schema/app-info/last-error/log/Tinker) when working in those services. Boost's
generic guidelines are advisory; the per-service `CLAUDE.md` is authoritative where they differ. See ADR-22.

---

## 8. Common development tasks

| I want to… | Do this |
|---|---|
| Add a core-api endpoint | `/make-endpoint POST events` (reads `services/core-api/CLAUDE.md` + sibling files) |
| Add full CRUD for a model | `/crud Event` |
| Add a status enum | `/add-enum EventStatus draft published ongoing completed cancelled` |
| Build the payment gateway sim | Open `services/payment-service/CLAUDE.md`, follow §Gateways |
| Add a notification type | Open `services/notification-service/CLAUDE.md`, follow §Jobs |
| Wire a cron job | core-api `routes/console.php` / scheduler — see core-api `CLAUDE.md` §Cron |
| Review money code before commit | invoke `financial-logic-reviewer` |
| Finish a unit of work | `/format-and-test`, then `/update-worklog` |

---

## 9. Definition of done (every change)

1. Follows the layering and conventions in the relevant service `CLAUDE.md`.
2. Has meaningful tests (core-api: order processing, payout calc, inventory — these are required).
3. Formatter clean (Laravel: `composer format` / Pint; Node/Next: `npm run lint`/`prettier`).
4. No secrets/PAN/OTP/token in code, logs, tests, or responses.
5. `WORKLOG.md` updated with what changed and why.

---

## 10. Git commit convention (gitmoji)

Every commit message **starts with a [gitmoji](https://gitmoji.dev)** then a concise, imperative summary:
`:emoji: <what changed>` (e.g. `:sparkles: Add ticket checkout with 15-min hold`). Keep the subject ≤ ~72 chars;
add a body when the "why" isn't obvious. Commit per logical unit of work, not one giant commit.

Pick the emoji that matches the change (most-used in this repo):

| Gitmoji | Use for |
|---|---|
| `:sparkles:` | New feature / endpoint |
| `:bug:` | Bug fix |
| `:white_check_mark:` | Add or update tests |
| `:memo:` | Documentation (docs/, CLAUDE.md, README) |
| `:art:` | Structure / formatting / following conventions (e.g. Pint) |
| `:recycle:` | Refactor (no behaviour change) |
| `:card_file_box:` | Database — migrations, schema, ERD |
| `:seedling:` | Seed data |
| `:lock:` | Security / auth / redaction / data-protection fixes |
| `:zap:` | Performance (e.g. indexing, locking efficiency) |
| `:wrench:` | Config (docker-compose, .env.example, providers) |
| `:truck:` | Move / rename files |
| `:fire:` | Remove code or files |
| `:construction:` | Work in progress (avoid on shared branches) |
| `:tada:` | Initial commit / project bootstrap |
| `:arrow_up:` / `:arrow_down:` | Upgrade / downgrade dependencies |
| `:rewind:` | Revert changes |

Examples:
```
:tada: Initialize EventHub monorepo scaffolding
:card_file_box: Add events and ticket_types migrations
:sparkles: Add vendor payout request + approval flow
:lock: Redact sensitive params in request logging
:white_check_mark: Cover concurrent-checkout oversell prevention
:memo: Fill requirement-analysis assumptions and edge cases
```

## 11. Where to look next

- **Plan & priorities:** [`PLAN.md`](./PLAN.md) — 5-day phased breakdown, priority matrix, team-delegation plan.
- **AI workflow playbook:** [`docs/ai-workflow.md`](./docs/ai-workflow.md) — how to drive the build with Claude Code, day-by-day prompts, and how a new dev uses the skills.
- **Progress & decisions:** [`WORKLOG.md`](./WORKLOG.md).
- **Planning deliverables:** [`docs/requirement-analysis.md`](./docs/requirement-analysis.md),
  [`docs/system-architecture.md`](./docs/system-architecture.md),
  [`docs/technical-decision-log.md`](./docs/technical-decision-log.md),
  [`docs/development-plan.md`](./docs/development-plan.md), [`docs/erd.md`](./docs/erd.md).
- **Per-service rules:** each `services/*/CLAUDE.md` and `frontend/CLAUDE.md`.
