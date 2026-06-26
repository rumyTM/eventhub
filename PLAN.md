# EventHub — Master Execution Plan & Checklist

> The day-to-day tracker for building EventHub. Drive the build from here: pick the current day, run `/day-plan <N>`,
> work the checklist, then `/format-and-test` and `/update-worklog`. Narrative + delegation view is in
> [`docs/development-plan.md`](./docs/development-plan.md); decisions in
> [`docs/technical-decision-log.md`](./docs/technical-decision-log.md); progress in [`WORKLOG.md`](./WORKLOG.md).

**Guiding principle (from the brief):** a well-planned, partially-implemented, well-documented, *tested* system beats
a "complete" untested one. Money correctness (idempotency, locking, audit, no double-charge/pay/oversell) outranks
feature breadth. Prioritise ruthlessly.

---

## Priority matrix (must-have vs nice-to-have)

**Must-have (do not submit without):**
- Auth + roles + ownership boundaries (admin/vendor/attendee).
- Event + ticket-type CRUD; event lifecycle.
- Order/checkout with 15-min hold + **distributed locking** (oversell prevention).
- payment-service: ≥2 simulated gateways, charge, webhook callback, **idempotency**, shared-secret auth.
- Payout calculation (commission + minimum threshold) and refund policy (100/50/0%).
- `ReleaseExpiredHolds` cron (the hold-expiry safety net).
- **Unit tests:** order processing, payout calc, inventory — these are explicitly required.
- All planning docs + CLAUDE.md + agent skills + README + docker-compose + seed data + API docs.

**Should-have:**
- notification-service (queue + retry/backoff + DLQ + delivery tracking) and the other cron jobs.
- Functional frontend (vendor/attendee/admin), checkout flow with hold countdown.
- QR check-in, admin analytics, dispute/refund queue.

**Nice-to-have (cut first if time runs short):**
- Ticket transfers, waitlist processing, per-vendor commission overrides, richer analytics, polished UI.

---

## Day 1 — Plan & architect  ▢
- [ ] `docs/requirement-analysis.md` — user stories (3 roles), assumptions, **edge cases**, priority matrix, risks.
- [ ] `docs/system-architecture.md` — service diagram, comms/auth matrix, the 4 key API contracts, job design, resilience.
- [ ] `docs/erd.md` — finalise schema + relationship/index/audit/soft-delete notes.
- [ ] `docs/technical-decision-log.md` — fill ADR-01..10 reasoning + trade-offs.
- [ ] `docs/development-plan.md` — phasing + team-delegation plan.
- Done when: a reviewer can understand the whole system from `docs/` alone. → `/update-worklog`.

## Day 2 — Scaffold + schema + core CRUD  ▢
- [ ] `/scaffold-service core-api`, `/scaffold-service payment-service` (Laravel base, ApiResponse, exception shaping, repo provider, Laravel Boost dev-only — ADR-22).
- [ ] `docker-compose.yml` boots mysql + redis + both Laravel services; health checks pass.
- [ ] Migrations for all entities (see `docs/erd.md`); enums (`/add-enum`).
- [ ] Auth: Sanctum, `role` enum, `EnsureRole` middleware, registration/login.
- [ ] `/crud Event`, `/crud TicketType` (with ownership + lifecycle rules).
- [ ] Vendor onboarding + KYC status endpoints.
- Done when: can register each role, create a vendor event with ticket types via API. → `/format-and-test`, `/update-worklog`.

## Day 3 — Orders, locking, payments, financial tests  ▢  ← highest risk/value
- [ ] Checkout: create order + holds, **distributed lock** + check-inside-txn, 15-min `expires_at`.
- [ ] payment-service: `PaymentGatewayContract` + StripeSim/PayPalSim (configurable rates), `/payments`, **idempotency**.
- [ ] core-api → payment-service client (shared secret + Idempotency-Key) in a queued job.
- [ ] Webhook callback (signed) → flip order to paid, issue tickets+QR, ledger entry, enqueue confirmation.
- [ ] Refund execution (policy in core-api, execution in payment-service); payout execution endpoint.
- [ ] `ReleaseExpiredHolds` cron.
- [ ] **Unit tests:** hold/expiry, **concurrent oversell**, idempotent checkout, idempotency in payment-service, payout calc, inventory.
- [ ] Run `financial-logic-reviewer` over the money paths.
- Done when: full purchase works end-to-end and money tests are green. → `/format-and-test`, `/update-worklog`.

## Day 4 — Notifications, cron, frontend  ▢
- [ ] `/scaffold-service notification-service` (Node + BullMQ); job types, retry/backoff, DLQ, delivery tracking.
- [ ] core-api publishes notification jobs (NotificationPublisherContract); vendor webhook registration + signed delivery.
- [ ] Remaining cron: `ProcessPayoutBatch` (no double-pay), `SendEventReminders`, `GenerateSalesReport`, `ProcessWaitlist`.
- [ ] `/scaffold-service frontend`; vendor dashboard, attendee pages + checkout (hold countdown), admin panel.
- Done when: a notification fires on order, payout batch runs safely, UI completes a purchase. → `/format-and-test`, `/update-worklog`.

## Day 5 — Tests, docs, AI artifacts, video  ▢
- [ ] Broaden tests; ensure required suites pass; coverage of edge cases.
- [ ] Seed data: vendors, events, tickets, orders, payouts (realistic, demo-safe credentials).
- [ ] API docs: Postman collection or OpenAPI/Swagger — reviewer can test without reading source.
- [ ] Final pass on all `docs/`, root `README.md`, CLAUDE.md files; confirm setup instructions actually work from clean.
- [ ] Record 15–20 min video (architecture, 2–3 key decisions, live demo: create event → buy ticket → payout → refund, AI workflow, retrospective). Link in README.
- Done when: clone → `docker compose up` → migrate+seed → demo works for a stranger. → `/update-worklog`.

---

## Rubric coverage check (do before submitting)
- [ ] **Req analysis & product thinking (25%)** — edge cases beyond brief, 3-role stories, priority matrix, risks.
- [ ] **Architecture & design (25%)** — clean boundaries, inter-service auth/retry, audit trail, indexing, partial-failure handling.
- [ ] **Code quality (20%)** — layering, thorough error handling, meaningful tests, consistent API, security basics, correct locking.
- [ ] **AI workflow & DX (15%)** — CLAUDE.md gets a reviewer productive in 30 min; well-scoped skills; reproducible workflow.
- [ ] **Tech leadership (15%)** — decision log reasoning, shipping discipline, clear video, realistic delegation plan.
