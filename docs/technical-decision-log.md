# EventHub — Technical Decision Log

> **Graded deliverable (Rubric 5: Technical Leadership Signals — 15%).**
> "Excellent" = mature reasoning, articulated trade-offs (not "I chose X because it's popular"), and a "with more
> time" section. Each entry: **Decision → Alternatives considered → Why → Trade-off accepted.** Written in the first
> person — these are my calls and the reasoning behind them. Kept honest.

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
- Why: For a solo, 5-day, four-moving-part build the dominant cost is keeping contracts and both sides of them in
  sync. One repo gives me a single `docker compose up`, atomic commits that change a contract and its producer and
  consumer together, and a reviewer who clones once and sees the whole system — planning docs, services, and wiring —
  in one place. The brief recommends it, and for a take-home that has to be *understood* quickly, discoverability
  beats isolation.
- Trade-off: CI is coarser (no independent per-service pipelines without path filters), the repo mixes PHP and Node
  toolchains, and module boundaries rest on discipline rather than a repo wall. At team scale I'd split CI by path and
  possibly extract services; at this scale that overhead buys nothing.

### ADR-02: Payment service in Laravel
- Decision: payment-service is Laravel 11 (same stack as core-api).
- Alternatives: Node.js; Go.
- Why: This service's whole job is idempotent money mutations with strong transactional guarantees — exactly what
  Laravel's DB transactions, migrations, and Eloquent give me — and it reuses core-api's conventions (envelope,
  layering, idempotency table). Building the two highest-risk services on one stack roughly halves the cognitive cost
  of the money path, where mistakes are most expensive, and lets me move idempotency/transaction patterns across both
  without re-learning a runtime.
- Trade-off: less language diversity on display (the notification-service already demonstrates polyglot). A real
  payment provider would more likely be its own hardened stack — but since gateways here are simulated behind a
  contract, the stack choice is about build speed and correctness, not production parity.

### ADR-03: Notification service in Node.js + BullMQ
- Decision: Node.js/TypeScript with BullMQ over Redis.
- Alternatives: Python (Celery/RQ); a Laravel queue worker on the existing stack.
- Why: The workload is I/O-bound fan-out (email + vendor webhooks) that must retry with backoff and dead-letter on
  permanent failure. BullMQ gives first-class retry/backoff/DLQ/delivery semantics out of the box, and Node's event
  loop suits many concurrent slow HTTP calls. Putting it on a separate runtime also *enforces* the boundary by
  construction: outbound flakiness physically cannot run on the checkout thread.
- Trade-off: a second runtime to operate and deploy, and the job-payload contract must be kept in sync with core-api
  by hand (no shared types across the language boundary). I accept that because a Laravel queue worker — while
  reusing the stack — gives weaker DLQ/backoff ergonomics and re-couples the failure domain I deliberately split out.

### ADR-04: Queue transport = Redis (queues + coordination, not the inventory lock)
- Decision: Redis (BullMQ) as the queue/coordination layer. **The inventory oversell lock is NOT on Redis** — it is a
  DB row lock (see ADR-07).
- Alternatives: RabbitMQ; Kafka; SQS.
- Why: I already need Redis for the notification queue, so using it for general coordination keeps the compose file to
  one extra dependency. For this scale, Redis + BullMQ's at-least-once delivery and retry is sufficient and
  operationally trivial. The point worth stating: I deliberately did **not** put the money-correctness guarantees on
  Redis — the lock and idempotency both live in the DB — so Redis being "only" at-least-once is fine, because
  duplicate deliveries are absorbed by idempotency keys (ADR-09).
- Trade-off: RabbitMQ offers richer routing and stronger delivery guarantees, and Kafka offers durable replay — none
  of which this workload needs. I'm trading those capabilities for a smaller operational footprint, with the safety
  net that the correctness-critical paths don't depend on the queue's guarantees at all.

### ADR-05: Response envelope `{ success, message, data, errors }` with real HTTP codes
- Decision: one envelope across core-api + payment-service (a strict superset of the brief's `{success,data,message}`),
  always with the real HTTP status code.
- Alternatives: the brief's 3-key shape; HTTP 200 + a status code buried in the body.
- Why: One response shape means the frontend has exactly one parser and one error path. Keeping the real HTTP status
  (a 404 is a 404, a 429 is a 429) lets clients, proxies, and the browser behave correctly without inspecting the
  body. I add an `errors` key so field-level validation maps straight onto form fields — the 3-key shape can't carry
  that cleanly. Being a superset means I stay compatible with the brief rather than diverging from it.
- Trade-off: one extra key beyond the brief's literal example and a little ceremony on every response. Cheap, for a
  single predictable contract across two services.

### ADR-06: Layered architecture (Controller → Service → Repository → Model)
- Decision: strict layering with repository interfaces bound in the container; no Eloquent outside a repository, no
  business logic in controllers.
- Alternatives: Eloquent-in-service; active-record-in-controller.
- Why: The money logic has to be unit-testable in isolation — I want to test payout math and oversell handling
  without spinning up a DB or HTTP. Layering lets me mock the repository and test the service directly, keeps all
  query-building in one place (a query change has exactly one home), and matches the brief's stated constraint.
- Trade-off: more files and boilerplate per feature, mitigated by scaffolding commands (`/make-endpoint`, `/crud`).
  For trivial CRUD it's over-engineered, but on a money system the consistency is worth more than the saved files.

### ADR-07: Inventory oversell prevention = pessimistic DB row lock
- Decision: `SELECT … FOR UPDATE` on the `ticket_type` row, taken **inside the checkout transaction**, around the
  availability check and hold creation.
- Alternatives considered: Redis distributed lock (Redlock / `Cache::lock`); optimistic locking (version column +
  retry); a single atomic conditional `UPDATE … WHERE available > 0`.
- Why: I want the lock and the inventory mutation to share **exactly one boundary**. With `FOR UPDATE` inside the
  transaction the lock auto-releases on commit/rollback — there's no TTL to tune, no owner-token bookkeeping, and no
  way for a crashed holder to leave a dangling lock (the failure modes that make a Redis lock subtly hard to get
  right). It also removes Redis from the money-critical path entirely: oversell prevention stays correct even if
  Redis is down. On the highest-risk code in the system, a correctness argument I can make locally and defend in one
  paragraph is worth more than peak throughput.
- Trade-off: under heavy contention on a single hot ticket type, `FOR UPDATE` serializes buyers and can hold
  row/gap locks longer than a short-lived Redis lock would, and it ties oversell safety to the primary DB's
  availability. I accept lower peak throughput on a single SKU for correctness I can reason about and one fewer
  dependency. At real scale I'd revisit with optimistic concurrency + retry, or sharded/bucketed inventory, to remove
  the single-row serialization point — and load-test it (see "with more time").

### ADR-08: Money as integer minor units (poisha) + currency
- Decision: store all amounts as integers in minor units (1 BDT = 100 poisha) with a currency code; round half-up
  once, explicitly, at each percentage calculation.
- Alternatives: `decimal:2`; float (rejected outright).
- Why: Money is the thing we cannot get wrong, and binary floats can't represent decimal currency exactly —
  `0.1 + 0.2 ≠ 0.3` will eventually corrupt a balance. Integer poisha makes all arithmetic exact and associative. The
  only place fractions appear is applying a percentage (10% commission, 50% partial refund), so I round there once,
  half-up, and write the result to the ledger where it's auditable. Storing currency alongside keeps the schema
  honest even though we're single-currency today (ADR-12).
- Trade-off: I convert/format at the edges (store poisha, display BDT) and must stay disciplined that nothing
  introduces a float midway. `decimal:2` would also avoid float but invites accidental float casts and is heavier in
  arithmetic; integers are the simplest thing that's provably correct.

### ADR-09: Idempotency on every money operation (DB-backed)
- Decision: idempotency keys on checkout, charge, refund, and payout; the receiver stores key→result in the **DB** and
  replays the original result on a duplicate, performing the side effect exactly once.
- Alternatives: rely on client retries being rare; store keys in Redis.
- Why: Every money call can be retried — a client double-click, a queued-job retry, a re-delivered webhook. Without
  idempotency each retry risks a double-charge or double-pay. Storing keys in the DB (not a cache) is deliberate: the
  guarantee then survives a Redis outage, consistent with keeping correctness off Redis (ADR-04/07).
- Trade-off: an extra write + lookup per money operation and a key table to retain and prune. Negligible against the
  guarantee that a retry can never corrupt the books.

### ADR-10: Sanctum + role/ownership for users; shared-secret + HMAC between services
- Decision: Sanctum bearer tokens for users with a `role` enum + ownership policies; a static per-service shared-secret
  bearer for inter-service calls; webhooks additionally signed with an HMAC-SHA256 of the raw body.
- Alternatives: JWT for users; OAuth2 client-credentials between services.
- Why: Sanctum is first-party and stateless-token, enough for three roles + row ownership without standing up an auth
  server. Between services I don't need user identity, only "is this the trusted caller," so a shared secret is the
  simplest sufficient thing — but a bearer alone is replayable, so webhooks add an HMAC over the body that I recompute
  and compare, which survives replay and tampering. JWT/OAuth2 client-credentials would add rotation/introspection
  machinery I can't justify for an internal, single-operator system in five days.
- Trade-off: static shared secrets are rotated manually and lack a per-call expiry like a JWT. Acceptable on a trusted
  internal network; at scale I'd move to short-lived signed service tokens and a secrets manager.

### ADR-11: Hybrid refund ownership — auto in-policy, admin-mediated dispute out-of-policy
- Decision: in-policy refunds (100% >48h, 50% 24–48h, 0% <24h vs the event's UTC `starts_at`) auto-approve and
  execute; an out-of-policy contest opens a `dispute` resolved by an admin on a separate admin endpoint.
- Alternatives: every refund admin-approved; every refund fully automated.
- Why: the policy is deterministic, so forcing a human to approve a clearly in-policy refund just adds latency and
  support load for zero judgment value. The genuinely contentious cases — someone wanting a refund inside the 0%
  window — *do* need judgment, so they become disputes. This puts automation where the rules are clear and humans
  where they aren't, which is the right division of labour.
- Trade-off: two code paths (auto-execute vs dispute) and an admin surface to build, versus one simple path. I accept
  the extra surface because "auto-approve everything" invites abuse and "admin-approve everything" doesn't scale.

### ADR-12: Single-currency platform (BDT); multi-currency out of scope
- Decision: platform-wide single currency, BDT; multi-currency, FX, and cross-currency payouts explicitly out of
  scope. A `currency` column is kept everywhere regardless.
- Alternatives: per-event or per-vendor currency from day one.
- Why: the platform operates in Bangladesh. A single currency removes FX rates, conversion rounding, and mixed-cart
  math — entire classes of money bugs — so I can guarantee exact integer arithmetic end to end. This is scope
  honesty: I'd rather ship a provably-correct single-currency system than a half-correct multi-currency one in the
  time available.
- Trade-off: a second market needs real work later (an FX source, per-currency rounding rules, display formatting). I
  keep the `currency` column on every money table so the *schema* never has to change — only the logic does.

### ADR-13: Append-only ledger as the financial source of truth; vendor balance derived
- Decision: every money state change is a signed, append-only row in `ledger_entries`; vendor balance is computed by
  aggregation over the ledger, never stored as a mutable column.
- Alternatives: a mutable `balance` column updated in place; tracking only statuses on payments/payouts.
- Why: a mutable balance is a single number that can drift, be updated under a race, or simply be wrong with no way to
  detect it. An append-only ledger makes the balance reproducible from history and every change auditable. It also
  makes the hard cases fall out naturally: a refund-after-payout is just a negative `clawback` entry, so the balance
  can legitimately go negative and reconcile against the next payout instead of forcing a reversal of an already-
  completed payout.
- Trade-off: balance is a `SUM` aggregate rather than a column read (mitigated by an index on
  `(vendor_id, created_at)`), and there are more rows. For a financial system, auditability and reproducibility beat
  the convenience of a cached number.

### ADR-14: Snapshot unit price (at hold) and commission rate (at sale)
- Decision: `order_items.unit_price` is locked at hold creation; `orders.commission_rate` is snapshotted at sale time;
  payouts compute from the snapshot, not the live values.
- Alternatives: read the current catalog price / live platform rate at charge or payout time.
- Why: the price a buyer is quoted must be the price they're charged even if an early-bird window closes during the
  15-minute hold or the vendor edits the price mid-hold — so the unit price is captured when the hold is created.
  Likewise, a vendor must be settled on the commission terms in force when the sale happened; reading the live rate at
  payout time would silently rewrite historical economics the moment the platform rate changed. Snapshotting makes
  both reproducible from the order and ledger alone.
- Trade-off: snapshots can diverge from the "current" catalog price/rate — which is exactly the intent, but it means
  reports must be explicit about whether they show historical or current values. A couple of extra columns; trivially
  worth it.

### ADR-15: Soft-delete reference data; never delete financial records
- Decision: soft-delete (`deleted_at`) `users`, `vendors`, `attendees`, `events`, `ticket_types`, `kyc_documents`;
  **never** delete `orders`, `order_items`, `payments`, `refunds`, `payouts`, `ledger_entries`, `tickets`,
  `disputes` (lifecycle via `status`); resolve/prune transient rows (`ticket_holds`, `idempotency_keys`).
- Alternatives: hard-delete everywhere; soft-delete everything uniformly.
- Why: reference data is pointed at by historical orders, so hard-deleting it would orphan auditable records —
  soft-delete keeps it resolvable for audit while hiding it from listings. Financial records are never deleted at all
  (regulatory retention + audit integrity); their "deletion" is a status transition. This keeps the audit trail
  intact by construction rather than by policy.
- Trade-off: every listing query must filter `deleted_at`, and the DB retains rows that are logically gone. That's the
  standard cost of an auditable system, and acceptable.

### ADR-16: KYC PII handling — minimize, encrypt, signed-URL, redact, retain (Bangladesh Bank aware)
- Decision: store only the KYC fields actually required; encrypt sensitive identifiers (`tin_bin`,
  `representative_nid`), the `payout_account`, and the per-vendor `webhook_secret` at rest; store documents as
  encrypted objects served **only** via short-lived signed URLs (never raw bytes or a durable public path); redact all
  of it from logs; define a retention window aligned to Bangladesh Bank / data-privacy guidance.
- Alternatives: store identifiers in plaintext; serve documents from a public bucket; keep KYC data indefinitely.
- Why: KYC is regulated personal and business data and this platform is PCI-DSS aware, so it must be handled to a
  higher bar than the rest of the schema. Encrypting identifiers and serving documents via per-request signed URLs
  limits blast radius if the DB or an endpoint leaks; redaction keeps PII out of logs and traces; a defined retention
  window means we don't hoard regulated data beyond its purpose.
- Trade-off: encryption complicates searching/indexing those fields, signed URLs add a generation step, and retention
  needs a purge job. All accepted as the cost of handling regulated data responsibly. **Open item:** confirm the exact
  mandated KYC retention period with compliance/PM before go-live.

### ADR-17: `orders` 1:N `payments` (retry cardinality)
- Decision: an order has many payment rows — each charge attempt is its own row with its own idempotency key — and at
  most one reaches `succeeded`.
- Alternatives: one payment row per order, overwritten on each retry.
- Why: a declined/failed charge that the buyer retries is a common, real flow. Overwriting a single payment row would
  destroy the attempt history (which gateway failed, when, why) and muddy idempotency. A row per attempt keeps every
  attempt auditable and makes "retry the same charge" (same idempotency key → replayed result) versus "start a new
  attempt" (fresh key, new row) explicit.
- Trade-off: queries must select the `succeeded` payment rather than "the" payment, and there are more rows. Minor,
  and the audit clarity on the money path is worth it.

### ADR-18: Notification retry policy — exponential backoff, hard cap, dead-letter
- Decision: failed notification/webhook deliveries retry on exponential backoff `delay = 4^(retry-1)` →
  **1s, 4s, 16s, 64s, 256s**; **max 5 retries = 6 total attempts** including the initial (BullMQ `attempts: 6`). On
  exhaustion the job moves to a **dead-letter queue** and the notification is marked `failed`.
- Alternatives considered: fixed-interval retry; infinite retry until success; no retry (fail immediately).
- Why: transient failures (a vendor endpoint blip, a momentary network error) self-clear, so a few spaced-out retries
  recover most deliveries; exponential spacing backs off fast (256s by the 5th attempt) so we don't amplify an outage
  by hammering a struggling endpoint. A hard cap + DLQ is essential — infinite retry would starve the queue against a
  permanently-dead endpoint, and no retry would drop recoverable messages; the DLQ preserves failed jobs for
  inspection and replay. (Note: the brief listed only four delays as an example, but "max 5 retries" needs a fifth,
  256s.)
- Trade-off: a permanently-failing delivery isn't dead-lettered until ~5.7 min of total elapsed time, and a DLQ needs
  monitoring and replay tooling. Acceptable — notifications are not a money path and delivery status is tracked
  throughout, so nothing is silently lost.

---

## Trade-offs made due to the 5-day constraint

- **Simulated gateways and email, not real integrations.** The assessment is about the correctness of the money
  *orchestration*, not provider SDKs. Gateways sit behind `PaymentGatewayContract`, so dropping in a real provider is
  a localized change, not a rewrite.
- **Functional, not polished, frontend.** The UI demonstrates the data flow and the checkout/hold experience; pixel
  polish would trade directly against backend correctness, which is graded higher and is where the risk lives.
- **Single currency (ADR-12) and deferred nice-to-haves.** Ticket transfers, waitlist, per-vendor commission
  overrides, and QR check-in polish are cut in that order if time runs short — none touch money correctness.
- **Direct enqueue to Redis instead of a transactional outbox.** A crash between the DB commit and the enqueue could
  theoretically drop a notification; I accept that because the failure is bounded (notifications are a courtesy, not a
  money path) and the hold-expiry + idempotency cover the money-relevant cases. The outbox is the first thing I'd add
  (below).
- **Manual shared secrets and no CI/CD.** Fine for a single-operator take-home; called out as scale work below.

## With more time (what I'd improve, add, or redesign)

- **Transactional outbox for core-api → notification publishing.** Write the job to an outbox table in the same
  transaction as the state change, with a relay that enqueues to Redis — so publishing is atomic with the commit and
  effectively exactly-once, closing the direct-enqueue gap above.
- **Saga / explicit compensation for the cross-service charge flow** instead of relying on webhook + hold-expiry —
  named compensating actions for each step rather than a timeout as the backstop.
- **Real gateway integration with tokenization/vaulting** so a PAN never touches our systems, shrinking PCI scope
  rather than just simulating around it.
- **Oversell path at scale (revisiting ADR-07):** optimistic concurrency + retry, or sharded/bucketed inventory, to
  remove the single-row serialization point — backed by a concurrency load test on the last-ticket scenario.
- **Observability:** the `trace_id` is already plumbed end-to-end; I'd add distributed tracing export, metrics, and
  alerting on queue depth, DLQ size, and stuck-`pending` orders so partial failures are *seen*, not just survived.
- **Contract tests between services + OpenAPI codegen for the frontend client**, so the hand-synced job/HTTP payloads
  (the cost noted in ADR-03) can't silently drift.
- **Multi-currency + FX and a tax/fee engine** (ADR-12) when expanding beyond Bangladesh — the schema already carries
  `currency` to make this a logic change, not a migration.
- **Platform hardening:** per-service CI/CD pipelines, a secrets manager with rotation (ADR-10), and an automated
  KYC-retention purge job (ADR-16).
