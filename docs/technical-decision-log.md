# EventHub — Technical Decision Log

> **Graded deliverable (Rubric 5: Technical Leadership Signals — 15%).**
> "Excellent" = mature reasoning, articulated trade-offs (not "I chose X because it's popular"), and a "with more
> time" section. Each entry: **Decision → Alternatives considered → Why → Trade-off accepted.** The first entries are
> pre-filled from choices already locked in; expand them and add new ones as you build. Keep it honest.

## Format
```
### ADR-NN: <decision>
- Decision: ...
- Alternatives considered: ...
- Why: ...
- Trade-off accepted (esp. due to 5-day constraint): ...
```

---

### ADR-01: Monorepo, not multi-repo
- Decision: Single repository with `services/core-api`, `services/payment-service`, `services/notification-service`,
  `frontend`, shared `docs/` and a root `docker-compose.yml`.
- Alternatives: separate repos linked from a root README.
- Why: <!-- FILL: atomic cross-service changes, one setup path, easier review for a take-home; the brief recommends it. -->
- Trade-off: <!-- FILL: coarser CI, mixed languages in one repo. -->

### ADR-02: Payment service in Laravel
- Decision: payment-service is Laravel 11 (same as core-api).
- Alternatives: Node.js.
- Why: <!-- FILL: shared conventions/tooling with core-api, faster to build solo, idempotency/transaction primitives. -->
- Trade-off: <!-- FILL: less language diversity demonstrated. -->

### ADR-03: Notification service in Node.js + BullMQ
- Decision: Node.js/TypeScript with BullMQ over Redis.
- Alternatives: Python (Celery/RQ); a Laravel queue worker.
- Why: <!-- FILL: BullMQ's first-class retry/backoff/DLQ fits the requirement; demonstrates polyglot; event-loop suits
  I/O-bound webhook/email fan-out. -->
- Trade-off: <!-- FILL: second runtime to operate; payload contract must be kept in sync with core-api. -->

### ADR-04: Queue transport = Redis
- Decision: Redis (BullMQ) for notification jobs; Redis also for distributed locks.
- Alternatives: RabbitMQ.
- Why: <!-- FILL: one dependency for queue + lock + cache; simpler compose; sufficient for this scale. -->
- Trade-off: <!-- FILL: RabbitMQ has richer routing/delivery guarantees we don't need here. -->

### ADR-05: Response envelope `{ success, message, data, errors }` with real HTTP codes
- Decision: one envelope across core-api + payment-service (superset of the brief's `{success,data,message}`).
- Alternatives: brief's 3-key shape; HTTP 200 + body status code.
- Why: <!-- FILL: clients read HTTP status directly; field-level errors map to forms; consistency. -->
- Trade-off: <!-- FILL: one extra key vs the brief's literal example. -->

### ADR-06: Layered architecture (Controller → Service → Repository → Model)
- Decision: strict layering with repository interfaces bound in the container.
- Alternatives: Eloquent-in-service; active-record-in-controller.
- Why: <!-- FILL: testable services (mock the repo), single query-building layer, matches the brief's constraint. -->
- Trade-off: <!-- FILL: more files/boilerplate; mitigated by scaffolding commands. -->

### ADR-07: Distributed locking for inventory
- Decision: <!-- FILL: Redis lock OR DB row lock (SELECT ... FOR UPDATE) around check-and-decrement. State which and why. -->
- Alternatives: optimistic locking (version column); atomic DB decrement with a CHECK; advisory locks.
- Why: <!-- FILL -->
- Trade-off: <!-- FILL: lock contention vs correctness; fallback if Redis is down. -->

### ADR-08: Money as integer minor units (+ currency)
- Decision: store amounts as integers (minor units) with an ISO currency code.
- Alternatives: `decimal:2`; float (rejected).
- Why: <!-- FILL: no float rounding errors; explicit currency; safe arithmetic. -->
- Trade-off: <!-- FILL: formatting/conversion at the edges. -->

### ADR-09: Idempotency for all money operations
- Decision: idempotency keys on checkout, charge, refund, payout; stored key→result.
- Why: <!-- FILL: webhook/retry re-delivery must not double-charge/double-pay. -->
- Trade-off: <!-- FILL: extra storage + lookup per request. -->

### ADR-10: Sanctum + role/ownership; shared-secret + HMAC between services
- Decision: <!-- FILL -->
- Alternatives: JWT; OAuth2 client-credentials between services.
- Why / Trade-off: <!-- FILL -->

<!-- ADD MORE as you build: QR strategy, refund auto vs admin, soft-delete policy, frontend state lib, test strategy. -->

---

## Trade-offs made due to the 5-day constraint
<!-- FILL: what you deliberately simplified or skipped, and why it was the right call (e.g. functional-not-polished UI,
single currency, nice-to-haves deferred, simulated email/gateways). -->

## With more time (what I'd improve, add, or redesign)
<!-- FILL — be specific and senior:
- Real gateway integration + tokenization/vault; PCI scope reduction.
- Outbox pattern for core-api → notification publishing (exactly-once); transactional outbox vs direct enqueue.
- Saga/compensation for cross-service consistency instead of webhook + expiry.
- Observability: tracing across services, metrics, alerting on queue depth/DLQ.
- Multi-currency + FX; tax/fee engine.
- Optimistic concurrency + horizontal scaling of checkout; load tests for the oversell path.
- Contract tests between services; OpenAPI codegen for the frontend client.
- CI/CD, per-service deploy, secrets manager. -->
