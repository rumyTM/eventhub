# EventHub — Work Log

> Running, most-recent-first log of what changed, decisions made, verification, and what's next. Update at the end of
> every working session with `/update-worklog`. Significant decisions get promoted into
> [`docs/technical-decision-log.md`](./docs/technical-decision-log.md).

---

## 2026-06-29 — Day 2: Document PHP 8.4 runtime requirement (docs only)
**Maps to:** Day 2 setup-instruction accuracy. No code/migrations touched.

**What changed**
- Made the **PHP 8.4.1+** requirement explicit wherever setup is described, since the committed `composer.lock`
  resolves Symfony 8.x (`php >= 8.4.1`) even though `composer.json` allows `^8.2`. Chose to keep 8.4 and document it
  (vs. re-locking to Symfony ^7 for true 8.2 portability).
  - `README.md` — note in the Local/Laragon fallback section (8.2/8.3 fail `composer install`; docker `php:8.4-cli`
    needs no local PHP).
  - `CLAUDE.md` §5 — added a "PHP version" line to the run instructions.
  - `services/core-api/CLAUDE.md` — header `PHP 8.2+ → 8.4+` plus a runtime note.
- `docs/erd.md` — added `payouts.currency` to the ERD so the diagram matches the migration (honors "always record
  currency"; deviation was flagged in the schema task).

**Verification**
- Docs-only; no tests/formatter. Confirmed `composer.lock` contains `symfony/*` entries requiring `php >= 8.4.1`
  and the two Dockerfiles already pin `php:8.4-cli`.

## 2026-06-29 — Day 2: Docker verification (schema migrates on docker MySQL)
**Maps to:** Day 2 — "docker-compose boots mysql + redis + both Laravel services; health checks pass" (PLAN.md).
Proves the already-verified migrations apply on the **docker `mysql` host**, not just local Laragon.

**What changed (docker/.env wiring only — migrations untouched)**
- **`services/core-api/Dockerfile` + `services/payment-service/Dockerfile`:** bumped base image `php:8.3-cli →
  `php:8.4-cli`. The committed `composer.lock` was resolved on PHP 8.4, so `symfony/*` v8.1.1 (requires
  `php >=8.4.1`) failed `composer install` on the 8.3 image. `composer.json` allows `^8.2`; Laravel 11 supports 8.4.
- **`docker-compose.yml`:** mysql host-port mapping `"3306:3306"` → `"${MYSQL_HOST_PORT:-3307}:3306"` to avoid a
  clash with the host's Laragon MySQL on 3306. Inter-container traffic is unaffected (core-api always reaches
  `mysql:3306` on the compose network); only the host-side published port changed.

**Verification (docker)**
- `docker compose up -d --build mysql redis core-api` → `docker compose ps`: **mysql healthy, redis healthy,
  core-api up** (core-api has no healthcheck defined, so it reports "Up", which is its healthy state). Ports:
  mysql `3307→3306`, redis `6379`, core-api `8000`.
- `docker compose exec core-api php artisan migrate:fresh` → **24/24 migrations DONE, 0 errors** (4 base + 20
  domain) against the docker host. Tinker confirmed `host=mysql db=eventhub_core server_version=8.0.46
  migrations=24 tables=30` — i.e. the MySQL 8.0 container, not Laragon.
- `GET http://localhost:8000/api/v1/health` → **HTTP 200**, envelope
  `{"success":true,"data":{"service":"core-api","status":"ok"},"message":"core-api is healthy.","errors":null}`,
  with a `Log-Trace-ID` response header (trace middleware working).

**Next**
- Same as below (Day 2 continuation): repositories + Sanctum auth + `/crud Event`/`TicketType` + KYC endpoints +
  feature tests. When `payment-service` is scaffolded, bring it up too (Dockerfile already on php:8.4).

## 2026-06-29 — Day 2: Domain schema + Eloquent models (core-api)
**Maps to:** Day 2 — Scaffold + schema + core CRUD (PLAN.md) — the migrations/models slice.

**What changed**
- **20 domain migrations** added under `services/core-api/database/migrations/` (`2026_06_29_100001..100020`),
  one per entity group, in FK-dependency order: `vendors`, `attendees`, `kyc_documents`, `events`,
  `ticket_types`, `orders`, `order_items`, `ticket_holds`, `tickets`, `payments`, `refunds`, `payouts`,
  `payout_items`, `disputes`, `waitlist_entries`, `ledger_entries`, `idempotency_keys`, `settings`,
  `event_reminders`, `sales_reports`. `users` already carried ULID + `role` + soft-deletes from the scaffold
  (left as-is). All PKs are ULIDs (`$table->ulid('id')->primary()`); FKs use `foreignUlid`.
- **All ERD "Indexing strategy" indexes created with their exact names** (e.g. `idx_events_status_starts_at`,
  `idx_holds_type_status`, `idx_holds_status_expires_at`, `idx_ledger_vendor_created`, `idx_ledger_subject`,
  `idx_waitlist_type_status_pos`, `idx_payouts_vendor_status`, plus the `unique(...)` guards on
  `orders/payments/payouts.idempotency_key`, `tickets.qr_code`, `event_reminders(event_id,type)`,
  `sales_reports(report_date,vendor_id)`, `settings.key`, `idempotency_keys.key`). Named single-column FK
  indexes are created *before* the FK so one index backs both (no duplicates).
- **20 Eloquent models** created (+ `User` updated): `HasUlids` everywhere; `casts()` for every enum
  (reusing `app/Enums/*`), money as `integer` minor units, rates as `decimal:4`, JSON/array, and datetimes.
  Relationships match the ERD (incl. the second `users→vendors` reviewer link, polymorphic-subject note on
  `LedgerEntry`, `Refund hasOne Dispute`). `$fillable` set explicitly on every model (no mass-assignment holes).
- **PII handling wired into the models:** `vendors.tin_bin`, `representative_nid`, `webhook_secret` →
  `encrypted`; `payout_account` → `encrypted:array`; `kyc_documents.storage_path` → `encrypted`; all added to
  `$hidden`. Values used `[PLACEHOLDER]` only — no real secrets/PII.
- **Soft-delete policy applied exactly per ERD:** `SoftDeletes` on `users/vendors/attendees/events/`
  `ticket_types/kyc_documents` only. Financial/issued-artifact tables (`orders`, `payments`, `refunds`,
  `payouts`, `payout_items`, `ledger_entries`, `tickets`, `disputes`, `event_reminders`) are never deleted.
- **`ledger_entries` is strictly append-only:** `created_at` only (no `updated_at` column), model sets
  `const UPDATED_AT = null`; `amount` is a SIGNED `bigInteger`. `tickets` has `$timestamps = false` (no
  timestamp columns per ERD). **Vendor balance is derived** via `Vendor::balance()` = `SUM(ledger.amount)` —
  no balance column exists.
- **Config:** added `format` (Pint) and `test` scripts to `services/core-api/composer.json` (the scaffold
  hadn't defined `composer format` referenced by CLAUDE.md).

**Decisions (this session)**
- **Money columns are integer minor units** (`unsignedBigInteger`, signed `bigInteger` only for the ledger),
  rates `decimal(5,4)`; every money/rate table also stores `currency` (default `BDT`) — consistent with the
  poisha convention. *Note:* added `payouts.currency` (the ERD table omitted it) to honour the "always record
  currency" rule.
- **Named-index-before-FK pattern** so a single named index backs the FK (avoids MySQL's duplicate auto-index).
- **`sales_reports` NULL-platform-row caveat** left to app logic (`updateOrCreate`), per ERD — the composite
  unique only guards vendor-scoped rows in MySQL.
- **`idempotency_key` columns are non-null unique** (every order/charge/payout carries one).

**Verification**
- `php artisan migrate:fresh` applies **all 24 migrations** (4 base + 20 domain) cleanly against the dev DB.
  (Local run used Laragon MySQL on `127.0.0.1`; the committed `.env` targets the docker `mysql` host.)
- `composer format` (Pint) clean — 26 files auto-fixed (import ordering / factory-docblock FQCN), 0 remaining.
- **Tinker smoke test** (rolled back) confirmed: ULID PKs are 26 chars; `role`/`kyc_status` cast to enums;
  `tin_bin` is ciphertext at rest and decrypts back; `payout_account` round-trips as an array; `user->vendor`
  relation resolves; `Vendor::balance()` goes `0 → 4250` after sale(+5000)/commission(−750) ledger rows; the
  ledger row has `created_at` and **no** `updated_at`.

**Next**
- Continue **Day 2**: `RepositoryServiceProvider` bindings + repositories for `Event`/`TicketType`; Sanctum
  auth endpoints (register/login/logout/me) + `EnsureRole`-guarded route groups; `/crud Event`,
  `/crud TicketType` with ownership + lifecycle rules; vendor onboarding + KYC status endpoints. Then the
  required feature tests. Add model **factories** (referenced in `@use HasFactory` docblocks) when writing tests.

## 2026-06-28 — Day 1: Plan & architect (planning docs filled)
**Maps to:** Day 1 — Plan & architect (PLAN.md). Also initialized the git repo (was uninitialized).

**What changed**
- **Repo init.** Initialized git (a partial/corrupt `.git` with a stale lock was repaired), set the default branch to
  `main`, and made the first commit of the existing scaffolding (`788fe0e`). Confirmed `.gitignore` excludes real
  `.env`/`.idea`; only `.env.example` is tracked.
- **`docs/requirement-analysis.md`** — filled all `<!-- FILL -->`: scope, 3-role user stories, functional specs,
  documented assumptions, edge cases (concurrent-checkout race, currency rounding, refund abuse, refund-after-payout,
  price-lock-at-hold), priority matrix + cut order, risk analysis + PM flags. KYC wording aligned to `verified`.
- **`docs/erd.md`** — finalized Mermaid ERD (20 entities) + relationship/normalization/indexing/audit/soft-delete
  notes and a KYC PII-handling section. Added KYC fields + `kyc_documents`, `settings`, `event_reminders`,
  `sales_reports`; `orders.commission_rate` + `order_items.unit_price` snapshots; signed/append-only `ledger_entries`
  with `vendor_id`; `payments.idempotency_key`; `tickets.checked_in_by`; `disputes.resolved_by`/`refund_id`;
  waitlist claim window. Removed `personal_access_tokens` (domain tables only).
- **`docs/system-architecture.md`** — filled service-boundary justification, auth strategy (+ named rate limiters),
  the 4 API contracts + payment-service internal contracts, DB-design summary, background-job table, and §6
  partial-failure/resilience. Synced numbers to BDT/poisha; `orders` 1:N `payments`.
- **`docs/technical-decision-log.md`** — 18 ADRs with why + trade-off (first person), a 5-day-constraint section, and
  a substantive "with more time" section.
- **`docs/development-plan.md`** — phasing narrative, critical path, 3–4 dev / 2-week delegation plan (parallel
  streams, dependencies, integration checkpoints, CLAUDE.md onboarding, critical-path staffing).
- Commits: `47390e9`, `3a68b94`, `e697c8c`, `f7ca08b`, `c94ed69` (+ `51590cd` ai-workflow/erd-prompt tidy).

**Decisions (this session)**
- **Inventory oversell lock = hybrid** — a short-lived Redis lock per `ticket_type` (satisfies "distributed", cuts
  contention) fronting an **authoritative DB row lock** (`SELECT … FOR UPDATE` inside the checkout txn), so oversell is
  impossible even if Redis is down (fall back to DB-only). (Revised from an initial DB-only choice after comparing the
  earlier draft.) → ADR-07.
- **Idempotency is DB-backed** (`idempotency_keys` + payment-service DB), so it survives a Redis outage. → ADR-09.
- **Notification retry**: exponential backoff 1/4/16/64/256s, max 5 retries (6 total attempts), then DLQ. → ADR-18.
- **`orders` 1:N `payments`** (retry cardinality, ≤1 succeeded). → ADR-17.
- **ULID primary keys** (non-enumerable, time-sortable), not bigint. → ADR-19.
- **Payout reserve-for-refund + `payout_items`**; settle only past the refund window; clawback as fallback. → ADR-20.
- **Role auth = backed enum + `EnsureRole` + policies**, not spatie/laravel-permission (three fixed roles). → ADR-21.
- **Laravel Boost** adopted (dev-only) for core-api + payment-service AI-assisted dev; the project `CLAUDE.md` is
  authoritative over Boost's generic guidelines. → ADR-22.
- All promoted into `docs/technical-decision-log.md` (ADR-01..22); no pending promotions.

**Verification**
- Documentation-only session — no application code, so no tests/formatter to run.
- Per-doc checks: `grep` confirms **0 `<!-- FILL -->` markers** remain in any of the five planning docs; ERD Mermaid
  brace balance verified (20 open / 20 close); cross-doc consistency spot-checked (KYC `verified`, BDT/poisha,
  derived balance, 1:N payments).

**Next**
- Begin **Day 2**: `/scaffold-service core-api` and `/scaffold-service payment-service`; docker-compose boots
  mysql+redis+both Laravel services; migrations from `docs/erd.md`; Sanctum auth + roles + `EnsureRole`; `/crud Event`
  and `/crud TicketType`.

## 2026-06-27 — Day 0: AI command-center scaffold
**Maps to:** pre–Day 1 setup (AI workflow artifacts + repo skeleton).

**What changed**
- Created the EventHub monorepo skeleton: `services/{core-api,payment-service,notification-service}`, `frontend`,
  `docs/`, `.claude/{skills,commands,agents}`, and root files.
- Root `CLAUDE.md` (system map, comms/auth matrix, response envelope, run instructions, conventions, command index).
- Per-service `CLAUDE.md`: core-api (full Laravel standards + EventHub domain: lifecycle, holds/locking, payout,
  refund policy, cron), payment-service (gateways, idempotency, signed webhooks, inter-service auth),
  notification-service (BullMQ, retry/backoff, DLQ, delivery tracking), frontend (Next.js views, API client).
- Agent skills: `backend-core-api`, `payment-service`, `notification-service`, `frontend`, `laravel-api-endpoint`.
- Slash commands: carried over + annotated `make-endpoint`/`crud`/`add-enum`/`add-resource`/`format-and-test`; added
  `update-worklog`, `day-plan`, `scaffold-service`.
- Subagents: `laravel-code-reviewer`, `laravel-test-writer`, new `financial-logic-reviewer`.
- Planning-doc scaffolds in `docs/`: requirement-analysis, system-architecture (with seeded API contracts),
  erd (seeded Mermaid ERD), technical-decision-log (ADR-01..10 pre-seeded), development-plan (team delegation).
- `PLAN.md` (5-day checklist + priority matrix + rubric coverage) and this `WORKLOG.md`.
- Canonical Laravel convention stubs in `.claude/stubs/laravel/` (copied verbatim by `/scaffold-service`; single
  source of truth, one-time deterministic per-project setup):
  - `LogHelper` — correlation id stored in Laravel `Context` so ONE `trace_id` spans the whole journey (request ->
    queued job -> payment-service -> webhook -> notify job); auto-stamped on every log line, auto-propagated across
    the queue. Recursive redaction of sensitive keys. UUID ids; reuses a valid incoming `Log-Trace-ID` header.
    Deliberately NOT a static property (would leak across jobs in a long-running worker).
  - `AssignLogTraceId` middleware — sets the id once per request from the header or a fresh UUID, echoes it on the
    response. Registered at the front of the `api` group.
  - `ApiResponse` — `{success,data,message,errors}` envelope, metadata-only logging (no token/PII leakage).
  - Propagation wired in docs: outbound calls attach `LogHelper::traceHeaders()`; notification job payloads carry
    `trace_id` so the Node service logs under the same id.

**Decisions (locked in this session → see decision log)**
- Monorepo; payment-service in Laravel; notification-service in Node.js/BullMQ; queue + locks on Redis;
  envelope `{success,message,data,errors}` with real HTTP codes; docker-compose as primary run path.

**Verification**
- Structure + file tree created and listed. No code yet — nothing to test. (Pending: `docker-compose.yml` + root README.)

- Added `docs/ai-workflow.md` — the AI collaboration playbook (judgment-vs-drafting model, review loop, Day 1
  thinking prompts + draft prompts per doc, Days 2–5 execution prompts, the new-dev 30-min path). Linked from root
  CLAUDE.md and README; doubles as the rubric's "reproducible AI workflow / how a new dev uses the skills" artifact.

- Added a **gitmoji commit convention** to root CLAUDE.md §10 (emoji + imperative summary, with a mapping table for
  this repo's change types) and referenced it in `docs/ai-workflow.md` setup + safety habits.

**Next**
- Finish root `README.md` + `docker-compose.yml` (Day 2 prerequisites are mostly scaffolding).
- Begin **Day 1**: fill the planning docs to "Excellent" (edge cases, assumptions, risks, ADR reasoning).
- Then **Day 2**: `/scaffold-service core-api` and `/scaffold-service payment-service`, migrations, auth.
