# EventHub — Development Plan & Task Breakdown

> **Graded deliverable (Rubric 5).** How the work was phased and why, plus a realistic team-delegation plan (the
> rubric rewards accounting for dependencies, onboarding time, and integration points). The day-by-day execution
> tracker lives in [`../PLAN.md`](../PLAN.md); this is the narrative + delegation view.

## 1. How the work was phased (solo, 5 days)
<!-- FILL: narrative of what you tackled first and why. Suggested spine:
- Day 1: requirements, assumptions, architecture, ERD, decision log (think before build).
- Day 2: scaffold all services, DB migrations, auth/roles, core CRUD (events, ticket types).
- Day 3: the hard core — orders/holds/locking, payment-service + idempotency + webhook, financial unit tests.
- Day 4: notification service (queue/retry/DLQ), cron jobs, functional frontend (3 role areas).
- Day 5: tests, API docs (Postman/OpenAPI), seed data, polish docs, record video.
Explain the ordering: highest-risk/highest-value (money correctness) before breadth; documentation alongside, not after. -->

## 2. Sequencing rationale & critical path
<!-- FILL: the critical path runs through auth → events/tickets → orders/holds/locking → payment integration →
payouts/refunds. Notifications and frontend depend on stable contracts, so they follow. State what is on the critical
path vs what can slip without blocking everything. -->

## 3. Team delegation plan (3–4 devs, 2 weeks)
If this were a real team instead of solo-in-5-days, how the work divides into parallel streams.

### Streams
| Stream | Owner | Scope | Depends on |
|---|---|---|---|
| A. Platform foundation | Lead/Dev1 | Monorepo, docker-compose, auth/roles, response envelope, CI, shared contracts | — (unblocks all) |
| B. Core domain | Dev1/Dev2 | Events, ticket types, orders/holds/locking, inventory, cron | A (auth, envelope) |
| C. Payments | Dev2/Dev3 | payment-service, gateways, idempotency, webhooks, refund/payout execution | A; contract w/ B |
| D. Notifications | Dev3 | notification-service, queue/retry/DLQ, vendor webhooks, delivery tracking | A; job contract w/ B |
| E. Frontend | Dev4 | API client, vendor/attendee/admin views, checkout flow | A; API contracts from B/C |

### Parallelization & dependencies
<!-- FILL:
- Week 1: A first (1–2 days, everyone aligns on contracts), then B/C/D/E start in parallel against agreed API
  contracts (define them up front — see system-architecture.md — so streams don't block on each other).
- Integration points: B↔C (checkout↔charge, webhook), B↔D (job payloads), B/C↔E (API client). Schedule integration
  checkpoints end of week 1 and mid week 2.
- Onboarding: the CLAUDE.md files + scoped skills let a new dev be productive in <30 min without reading the whole
  codebase — each owns their service's CLAUDE.md.
- Risk buffer: reserve the last 2 days for end-to-end testing, the oversell/concurrency load test, and hardening. -->

### What I'd do differently with a team vs solo
<!-- FILL: contracts-first so streams parallelize; pair on the locking/idempotency code (highest risk); a dedicated
QA pass on money paths; code review gates via the financial-logic-reviewer agent; demo every Friday. -->

## 4. First real sprint (if this were a product)
<!-- FILL: what the first 2-week sprint would prioritise for a real launch — thinnest end-to-end money-correct slice
(one gateway, one ticket type, full audit) over breadth; instrument it; then expand. -->
