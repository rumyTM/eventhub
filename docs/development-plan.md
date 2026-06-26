# EventHub — Development Plan & Task Breakdown

> **Graded deliverable (Rubric 5).** How the work was phased and why, plus a realistic team-delegation plan (the
> rubric rewards accounting for dependencies, onboarding time, and integration points). The day-by-day execution
> tracker lives in [`../PLAN.md`](../PLAN.md); this is the narrative + delegation view.

## 1. How the work was phased (solo, 5 days)

The ordering principle is **risk-and-value first, breadth last** — the brief is explicit that a well-planned,
tested, money-correct partial system beats a feature-complete one that double-charges. So the schedule front-loads
the hardest, most irreversible work (the money path) and treats documentation as something written *alongside* the
build, not bolted on at the end.

- **Day 1 — Think before building.** Requirements, assumptions, edge cases, architecture, ERD, and the decision log.
  This is deliberately a full day with no code: the expensive mistakes on a money system are schema and
  contract mistakes, and they're far cheaper to fix in a doc than after orders reference the wrong columns. Locking
  these (integer poisha, DB row lock, append-only ledger, idempotency everywhere) up front means every later day
  builds on settled decisions.
- **Day 2 — Foundation that everything stands on.** Scaffold the services, wire docker-compose, write the migrations
  from the ERD, and build auth/roles/ownership + the core CRUD (events, ticket types, vendor/KYC). Nothing
  money-related works without auth and the domain tables, so this is the unblocking layer.
- **Day 3 — The hard core (highest risk/value).** Checkout with holds and the DB row lock, the payment-service with
  ≥2 gateways + idempotency, the signed webhook flipping orders to paid, refund/payout execution, the
  `ReleaseExpiredHolds` safety net — and the **required financial unit tests** (oversell, idempotent checkout, payout
  calc, inventory) written *with* the code, not after. If the week were cut short, this is the day that has to be
  solid.
- **Day 4 — Resilience and surface.** notification-service (queue/retry/backoff/DLQ), the remaining cron
  (payout batch, reminders, sales report, waitlist), and a functional frontend across the three roles. These depend
  on the Day-3 contracts being stable, so they come after.
- **Day 5 — Make it provable and demonstrable.** Broaden tests, write the API docs (Postman/OpenAPI) so a reviewer
  can exercise it without reading source, seed realistic demo data, final-pass all docs, and record the walkthrough
  video.

Documentation runs as a thread through every day (the decision log gains ADRs as choices are made, the worklog is
updated each session) rather than being a Day-5 scramble — which is also what keeps the docs honest.

## 2. Sequencing rationale & critical path

**The critical path** — the chain where each link genuinely blocks the next, and any slip slips the whole project:

```
auth/roles → events + ticket types → checkout/holds + DB row lock → payment integration (charge + idempotency + webhook) → refund/payout execution + ledger
```

Each step is a hard dependency of the next: you can't issue tickets without a paid order, can't have a paid order
without the charge+webhook round-trip, can't charge without an order created under the inventory lock, can't create
that order without events/ticket types, and can't own any of it without auth. This is where the senior attention and
the required tests go.

**Off the critical path (can slip without blocking everything):**
- **notification-service** — depends only on the job-payload *contract* being agreed, not on the money code being
  finished. It can be built in parallel against a stub and integrated late; if it's not done, checkout still works,
  buyers just don't get an email.
- **frontend** — depends on the API contracts (already specified in `system-architecture.md` §3), not the
  implementations. It can build against those contracts and a mock server, and is the safest thing to compress.
- **Most cron except `ReleaseExpiredHolds`** — the payout batch, reminders, sales report, and waitlist are
  "should/nice-to-have." `ReleaseExpiredHolds` is the exception: it's *on* the critical path because it's the
  inventory safety net that makes the hold design correct under failure.

So the rule is: never let a non-critical stream (notifications, UI polish, reports) consume time the critical path
needs, and define the contracts early enough that the non-critical streams can proceed without waiting on the
critical one.

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

The plan is **contracts-first**: Stream A spends days 1–2 locking the things every other stream consumes (auth +
response envelope + the inter-service API/job contracts already drafted in
[`system-architecture.md`](./system-architecture.md) §3). Once those are frozen, B/C/D/E proceed against the
*contracts*, not each other's code — which is what lets four people work without constantly blocking.

**Week 1**
- **Days 1–2 (A, whole team aligning):** monorepo + docker-compose boot, Sanctum auth/roles/ownership, the envelope,
  CI, and a written freeze of the four key contracts (create-event, checkout, charge+webhook, payout) and the
  notification job payload. Everyone reviews these together — this meeting is the cheapest bug-prevention of the
  project. Days 1–2 also double as **shared ramp-up/onboarding** time: while A is being built, the other owners read
  the root + per-service `CLAUDE.md` files and the relevant contract sections, so everyone starts day 3 already
  oriented.
- **Days 3–5 (B, C, D, E in parallel):**
  - **B (core domain)** builds events/ticket types, then checkout/holds + the DB row lock and inventory — the
    critical path. Calls C behind a thin client interface it can stub until C is ready, and owns
    `ReleaseExpiredHolds` (the one piece of cron that is on the critical path).
  - **C (payments)** builds the payment-service, gateways, idempotency, and the signed webhook against the frozen
    contract; can develop and test fully against its own contract without B being finished.
  - **D (notifications)** builds the queue/retry/backoff/DLQ machinery (ADR-18) and consumes the agreed job payload
    from a fixture; integrates with B's real enqueue later.
  - **E (frontend)** builds the API client + the three role areas against the contracts + a mock server.

  Because two owners are split across streams, week-1 priority is explicitly toward the critical path: **Dev2 leans
  into the B/C-critical work** and **Dev3 prioritizes C over D** (D is off the critical path and builds against
  fixtures until C is real), while the **lead floats to relieve whichever of B or C is slipping** — so the critical
  B↔C integration is never bottlenecked on one overloaded person.

**Week 2**
- **Integration + hardening.** B↔C goes live (real checkout→charge→webhook→paid), B↔D (real enqueue), B/C↔E (client
  against real endpoints). Then the money-path test pass: the oversell/concurrency load test, idempotency replay,
  refund/payout edge cases, end-to-end purchase. Last 2 days reserved as a buffer for hardening and the demo.

**Integration checkpoints (explicit, not "whenever"):**
| When | Checkpoint | Pass criteria |
|---|---|---|
| End of week 1 | Contracts demo'd against stubs | each stream calls the others' **stubbed** endpoints with the frozen payloads, green |
| Mid week 2 | Real B↔C↔D wired | a real checkout drives a real charge, webhook flips the order to paid, a notification job is enqueued |
| End of week 2 | End-to-end + load | full purchase→payout→refund through the UI; concurrency oversell test green; DLQ behaviour verified |

**Onboarding (why this is fast).** A new dev does **not** read the whole monorepo. The root
[`CLAUDE.md`](../CLAUDE.md) gives the system map and conventions in one read; each service's own `CLAUDE.md` plus the
scoped skills (`backend-core-api`, `payment-service`, `notification-service`, `frontend`) lets them be productive in
their stream in <30 minutes. A stream owner reads their service `CLAUDE.md` + the relevant contract section of
`system-architecture.md` and can start — they don't need C's internals to build B against C's contract.

**Risk buffer.** The last 2 days of week 2 are deliberately unallocated for feature work — they absorb integration
surprises, the oversell load test, and hardening. If everything is on time, that time goes to test breadth and the
nice-to-haves; if not, it's the slack that keeps the money path correct.

### What I'd do differently with a team vs solo

- **Contracts-first, formalized.** Solo, the contracts live in my head and in `system-architecture.md`. With a team
  I'd freeze them as real artifacts (OpenAPI + JSON-schema'd job payloads) on day 1 and generate clients from them, so
  the hand-synced payloads can't drift (the cost noted in ADR-03/ADR-17).
- **Pair on the highest-risk code.** The locking and idempotency logic (ADR-07/09) is where a subtle bug is
  catastrophic and hardest to spot in review — I'd pair-program it rather than have one person write and another
  rubber-stamp it.
- **A dedicated QA pass on money paths**, separate from the implementer — oversell, idempotent replay, refund-after-
  payout, payout double-pay — owned by someone whose job is to try to break it.
- **Review gates, not just reviews.** Money-touching PRs must pass the `financial-logic-reviewer` agent and a human
  before merge; CI runs the required financial tests on every PR.
- **A demo every Friday** against the integration checkpoints above, so "it works on my branch" is caught weekly, not
  at the end.

Solo, I compensate for the missing team with the same instincts in cheaper form: the decision log forces me to argue
trade-offs with myself, the scoped agents/skills act as a standing reviewer, and the integration risk is contained by
doing the critical path first while the contracts are freshest.

## 4. First real sprint (if this were a product)

The first sprint of a real product would ship the **thinnest end-to-end money-correct slice**, instrumented, rather
than a broad-but-shallow feature set:

- **One gateway, one ticket type, full audit.** A vendor can create one event with one general-admission ticket type;
  an attendee can buy it through a single real (tokenized) gateway; the charge, webhook, ledger, and a real payout all
  work — with the append-only ledger and idempotency in place from line one. Correctness is the feature.
- **Instrument it before expanding.** Ship with the tracing (the `trace_id` is already plumbed), metrics, and alerts
  on stuck-`pending` orders, queue depth, and DLQ size — so when we *do* add breadth, we can see what breaks.
- **Then expand along proven rails:** more ticket kinds and group bundles, refunds/disputes, the payout batch and
  threshold, notifications, then the nice-to-haves (waitlist, transfers, multi-currency). Each addition reuses the
  audited money core rather than bolting money logic onto a feature.

The discipline is the same as the 5-day build, just at product scale: **a small slice that never double-charges
beats a wide one that sometimes does**, and everything after the first slice is expansion, not re-architecture.
