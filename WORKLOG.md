# EventHub — Work Log

> Running, most-recent-first log of what changed, decisions made, verification, and what's next. Update at the end of
> every working session with `/update-worklog`. Significant decisions get promoted into
> [`docs/technical-decision-log.md`](./docs/technical-decision-log.md).

---

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
