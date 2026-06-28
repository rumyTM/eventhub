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

### ADR-04: Redis for queues, coordination, and the fronting inventory lock
- Decision: Redis (BullMQ) is the queue/coordination layer **and** the short-lived per-`ticket_type` lock that
  **fronts** the authoritative DB row lock (ADR-07).
- Alternatives: RabbitMQ; Kafka; SQS.
- Why: I already need Redis for the notification queue, so using it for general coordination keeps the compose file to
  one extra dependency. The point worth stating: the money-correctness **guarantees** live in the DB — the
  *authoritative* inventory lock is the DB row lock, and idempotency is DB-backed (ADR-09) — while Redis provides the
  **distributed front**: a short-lived per-`ticket_type` lock that satisfies the brief's "distributed" requirement and
  cuts `FOR UPDATE` contention on hot ticket types, plus at-least-once queue delivery. So Redis being only
  at-least-once, or briefly unavailable, is fine: duplicate deliveries are absorbed by idempotency keys (ADR-09), and
  a Redis-lock outage degrades to DB-only locking without losing correctness.
- Trade-off: RabbitMQ offers richer routing and stronger delivery guarantees, and Kafka offers durable replay — none
  of which this workload needs. I'm trading those capabilities for a smaller operational footprint, with the safety
  net that correctness never depends on Redis — only the contention optimization and delivery latency do.

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

### ADR-07: Inventory oversell prevention = hybrid lock (Redis lock + authoritative DB row lock)
- Decision: a **short-lived Redis lock per `ticket_type`** taken *around* the critical section, **plus** an
  authoritative **DB row lock** (`SELECT … FOR UPDATE` on the `ticket_type` row) *inside* the checkout transaction.
  The DB row lock is the correctness guard; the Redis lock is an optimization layered on top.
- Alternatives considered: DB row lock alone; Redis lock alone; optimistic versioning (version column + retry).
- Why: I want both the brief's stated requirement and a guarantee I can defend. The Redis lock literally satisfies the
  **"distributed" requirement** — it serializes contending checkouts across multiple core-api workers *before* they
  reach the database, which also **cuts DB lock contention on hot ticket types** (popular events) by thinning the
  herd that hits `FOR UPDATE`. But Redis is not trusted for correctness: the **DB row lock inside the transaction
  remains authoritative**, so oversell is impossible **even if Redis is unavailable** — we simply fall back to
  DB-only locking and lose the contention optimization, not the guarantee. I rejected **Redis-alone** precisely
  because a lock hiccup or a TTL expiring mid-critical-section could let two workers oversell — unacceptable on the
  money path. I rejected **optimistic versioning** because it thrashes (compare-and-retry storms) under exactly the
  high-contention popular-event case we most need to handle well.
- Trade-off: two coordination mechanisms instead of one — more moving parts to reason about and operate. I accept
  that because the responsibilities are cleanly split: correctness lives entirely in the DB row lock, and Redis is a
  pure performance optimization that can fail open without compromising correctness. At larger scale I'd add
  sharded/bucketed inventory and load-test the last-ticket path (see "with more time").

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
- Why: KYC is regulated **personal and business data (PII)** — NID, TIN/BIN, bank account — governed by data-privacy
  law and **Bangladesh Bank KYC/AML obligations**, *not* by PCI-DSS (which covers cardholder data this platform never
  stores). That regulatory weight is what demands a higher bar than ordinary tables. Encrypting identifiers and
  serving documents via per-request signed URLs limits blast radius if the DB or an endpoint leaks; redaction keeps
  PII out of logs and traces; a defined retention window means we don't hoard regulated data beyond its purpose.
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

### ADR-19: ULID primary keys, not auto-increment bigint
- Decision: ULIDs for all primary keys (Laravel `HasUlids`), with `foreignUlid` foreign keys — not auto-increment
  bigint.
- Alternatives considered: bigint auto-increment; UUIDv4.
- Why: in a multi-tenant money app, sequential integer IDs are **enumerable** — they leak counts and let one tenant
  guess another's resources (vendor A incrementing through order IDs to probe vendor B's orders). ULIDs are
  **non-enumerable**, closing that whole class of IDOR/enumeration risk. They're also **time-sortable** (lexicographic
  = chronological), so unlike random **UUIDv4** they preserve index locality — new rows append to the end of the
  index instead of scattering random inserts across the B-tree, which keeps write performance and page utilization
  healthy.
- Trade-off: larger keys (16 bytes vs 8 for bigint) and therefore slightly larger indexes and foreign keys.
  Negligible at this scale, and a price I'll happily pay for the multi-tenant security posture — guessable IDs on a
  payments platform are not something I want to defend later.

### ADR-20: Defer settling revenue until the event is completed; reserve-for-refund; clawback as fallback
- Decision: an order's revenue is **not settled** in a payout until the **event is marked `completed`** — i.e. it
  actually happened. Payouts carry a `reserved_refund` amount and a `payout_items` link to the exact orders they
  settle. A refund that still slips past settlement (a post-event dispute override) is handled by the existing
  **negative `clawback` ledger entry** (ADR-13), netted into the vendor's next payout.
- Alternatives considered: settle immediately and always claw back; settle once each order is past its refund window
  (event `starts_at` − 24h) while the event may still be cancelled.
- Why: the cleanest way to avoid clawing back money is to **not pay out money that might still be refunded in the
  first place** — and the strongest version of that is to settle only once the event has actually **happened**. Gating
  on event completion (not merely on the per-order refund window) means a **cancelled or no-show event never produces
  a paid-then-clawed-back vendor**: the money simply was never settled. This eliminates the common refund-after-payout
  case entirely, so clawback drops to a rare fallback for genuinely exceptional post-event events (e.g. an admin
  dispute override). `payout_items` makes every settlement **traceable to the exact orders** it covers, so the books
  reconcile and an auditor can follow every cent from an order to its settlement.
- Trade-off: vendors wait longer for funds — revenue settles only after the event completes, not at sale — accepted as
  the cost of never paying out money that might still be refunded, and it pairs cleanly with the cancellation refund
  policy (ADR-23) where the vendor, not the platform, bears a cancellation.

### ADR-21: Role authorization via a backed enum, not spatie/laravel-permission
- Decision: role-based access is a string-backed PHP enum on `users` (`admin|vendor|attendee`) + an `EnsureRole`
  middleware for route gating + policies for row-level ownership. Not spatie/laravel-permission.
- Alternatives considered: spatie/laravel-permission; a hand-rolled roles/permissions table.
- Why: there are exactly three fixed roles, one per user, with no dynamic or granular permissions — no admin-defined
  roles, no multi-role users, no permission-management UI. An enum + middleware + policies is sufficient, on-convention
  (I use backed enums for every fixed value set), and avoids three tables and a dependency for no benefit. The hardest
  authorization requirement — vendor A must never see vendor B's data — is **row ownership**, solved by policies and
  query-scoping on `vendor_id` regardless of the role mechanism, so spatie wouldn't even address it.
- Trade-off: admin-defined roles or granular per-user permissions would mean migrating to spatie/laravel-permission
  (noted in "with more time"). For this scope, adopting it now would be over-engineering.

### ADR-22: Laravel Boost for AI-assisted Laravel development (dev-only)
- Decision: install Laravel Boost (`composer require laravel/boost --dev`; `php artisan boost:install`) in core-api
  and payment-service — Laravel's first-party AI toolkit (MCP server + guidelines). Dev-only; not in the Node
  notification-service or Next.js frontend; never shipped to production.
- Alternatives considered: no AI tooling beyond my hand-authored CLAUDE.md + skills; relying on generic web docs.
- Why: the brief makes AI-augmented development non-negotiable and grades AI workflow/DX at 15%. Boost's
  version-accurate `search-docs` (scoped to my installed Laravel 11 + package versions) sharply cuts the main
  AI-on-Laravel failure mode — writing APIs from the wrong version — and its live DB-schema/app-info/last-error/log/
  Tinker introspection lets the agent inspect real state instead of guessing, which directly improves correctness on
  the money path. Committing it demonstrates a structured, reproducible AI workflow.
- Trade-off: a dev dependency plus a local MCP server to run, and Boost's auto-generated guidelines are generic and
  can drift from this project's stricter conventions (layering, hybrid lock, ULID, ledger, envelope) — so the project
  CLAUDE.md is declared authoritative where they differ. Applies only to the two Laravel services.

### ADR-23: Event cancellation refunds 100%, funded by the vendor; platform refunds its commission too
- Decision: a vendor/admin-cancelled event refunds **every attendee 100%** (policy-overridden, ignoring the normal
  100/50/0% time bands). The refund is **funded by debiting the vendor** (a negative `clawback` ledger entry), and the
  **platform also refunds its own commission** — it earns nothing on a cancelled event.
- Alternatives considered: platform keeps its commission (the vendor absorbs the full refund); platform eats the
  refund (covers it itself).
- Why: the **vendor caused the cancellation**, so they bear the cost — not the platform, and certainly not the
  attendee, who must be made whole. Refunding our own commission too is the fair, trust-preserving choice (charging a
  fee on an event that never happened would be indefensible) and it keeps the ledger **symmetric** — the original sale
  and its full reversal net to exactly zero. Funding the refund by **debiting the vendor through the ledger** means the
  balance can go negative and reconcile against future sales (ADR-13) rather than reversing already-completed payouts.
- Trade-off: a vendor balance can go **negative** after a cancellation. Acceptable — it's the honest accounting, and
  because settlement only happens after the event completes (ADR-20) we usually **haven't paid the vendor out yet**
  anyway, so in the common case the negative balance is netted before any real money left the platform.

### ADR-24: Checkout mechanics — Idempotency-Key via header, group-bundle pricing, cart normalization
- Decision (a cluster of small checkout choices that build on ADR-07/09):
  - **Idempotency-Key is an HTTP header**, *required* (missing → 422), folded into validated data so the same key
    always maps to the same order. A replay with the same body returns the **same order** (no new holds/order); a
    replay with a **different body → 409**. The DB `idempotency_keys.key` + `orders.idempotency_key` unique indexes
    are the backstop, and a concurrent duplicate that loses the insert race is caught and resolved as a replay.
  - **Group-bundle pricing rule:** if a ticket type has `group_size` set and the line `quantity >= group_size`,
    **every unit on the line** is priced at `round(price × (1 − group_discount))` (half-up, integer poisha), not just
    whole multiples of `group_size`; otherwise `unit_price = price`. Implemented as a pure `ResolveTicketPrice` action
    so it's unit-testable without a DB.
  - **Duplicate cart lines are merged** (summed) per `ticket_type_id` before the availability check, so two lines of
    the same type can't slip past the check by being counted separately; ids are sorted for deterministic lock order.
  - **Cache-lock contention → 409** (`LockUnavailableException`): if the short-lived per-`ticket_type` lock can't be
    acquired within a few seconds, fail fast and let the client retry, rather than queueing indefinitely.
- Alternatives: idempotency key in the body (easy to forget / collide with payload); discount only on exact multiples
  of group_size (surprising for buyers); failing checkout on duplicate lines (worse UX than merging).
- Why: a required header key is the conventional, hard-to-misuse contract for money endpoints and keeps the dedupe
  identity out of the business payload. Merging lines + sorted lock acquisition removes two subtle correctness traps
  (double-spend within one cart, lock-order deadlock). Surfacing lock contention as a retryable 409 keeps request
  latency bounded. The pricing rule is written down exactly because "discount applies to the whole line once the
  threshold is met" is a product choice, not an obvious default.
- Trade-off: returning the same order on replay uses HTTP **201 both times** (not 200-on-replay) — the client treats
  the order resource identically, so I favoured one predictable path over a status-code distinction. Holding multiple
  cache locks across the cart's transaction slightly raises contention on multi-type carts; acceptable because the
  locks are short-lived and the DB row lock (not the cache lock) is the correctness guard (ADR-07).

### ADR-25: Webhook settlement honors only non-expired holds — a late successful charge never oversells
- Decision: when the payment webhook reports **success**, core-api issues tickets only if the order's reservation is
  **still live**. `convertActiveForOrder` converts holds that are both `active` **and** `expires_at > now()`, and the
  service treats a zero-conversion result as "reservation lapsed": it does **not** issue tickets, does **not** move
  `quantity_sold`, and does **not** write a sale ledger row. The order is left `pending` for the `ReleaseExpiredHolds`
  safety net to expire. The charge result is still recorded faithfully (the `payments` row → `succeeded`), so a
  captured-but-unfulfilled charge is discoverable and becomes a **refund/reconciliation concern in a later slice**.
- Alternatives: (a) convert any `active` hold regardless of `expires_at` and issue — rejected, it would issue against
  inventory the system freed at read time (ADR-07's read-time expiry), i.e. **oversell**; (b) mark the order paid and
  let support sort out the missing seats — rejected, worse customer + audit outcome than leaving a recorded
  succeeded-charge for automated refund.
- Why: a slow gateway or delayed/retried webhook can confirm a charge after the 15-minute window. ADR-07 already
  treats a hold past `expires_at` as freed at **read time** (before `ReleaseExpiredHolds` sweeps it), so the only
  correct behavior on a late success is to refuse issuance and refund. This extends ADR-07's invariant into the
  webhook path so "no oversell, ever" holds even in the charge-after-expiry race.
- Trade-off / assumption: the guard uses a count (`convertActiveForOrder(...) === 0`) as an all-or-nothing signal.
  This is correct **because all holds for one order share a single `expires_at`** (created together at checkout), so
  they expire simultaneously. If holds could ever expire heterogeneously within one order, this check would let a
  partially-expired multi-line order through and oversell the lapsed line; a per-line reconciliation of converted
  count vs. issued quantity would then be required. Documented so a future change to hold lifetimes revisits it.

### ADR-26: No order is marked paid without its payment of record
- Decision: a **success** webhook whose `payment_ref` matches **no** core-api `payments` row for that order
  (`findByExternalRefForOrder` → null) is a **logged no-op** — the order is not marked paid, no tickets are issued,
  no ledger is written. The `external_ref` is persisted at charge initiation (Chunk C), so a legitimate success
  always has a matching row; a miss means an unreconcilable or misrouted callback.
- Alternatives: trust the (HMAC-authenticated, amount-matched) payload and settle anyway — rejected: it would create
  a paid order with no payment record, breaking the audit chain that links money movement to a gateway reference.
- Why: the webhook is already authenticated and amount-checked, but settling without a payment of record severs the
  one-to-one audit link between an order's paid state and a recorded charge. Failing loud (warn + no-op) preserves
  ledger/audit integrity and lets the sender's retry/DLQ surface the anomaly. Pairs with `CalculateCommission`
  rejecting a blank/non-numeric `commission_rate` rather than silently settling zero commission — both refuse to
  mis-settle money on bad/missing inputs.
- Trade-off: a genuinely lost payment row (e.g. data loss before the webhook) leaves a paid-at-gateway charge
  un-fulfilled until reconciliation; acceptable because the recorded gateway success (ADR-25) plus the warning log
  make it detectable, and silently fabricating a paid order would be worse.
- Related guard (same "refuse to mis-settle" family): if an order item's `ticket_type`/`event` is soft-deleted
  **between checkout and settlement**, `vendor_id` resolves to null. Rather than issue tickets while silently
  dropping that vendor's ledger rows (money with no audit record), settlement throws
  `OrderSettlementIntegrityException` **inside** the transaction — the whole settlement rolls back (no tickets, no
  `quantity_sold`, no ledger), bubbles as a loud 500, and the order is left `pending` for reconciliation/expiry.

### ADR-27: End-to-end tests fake only the true process boundary
- Decision: the purchase-loop e2e test (`PurchaseLoopEndToEndTest`) drives **real** core-api code at every hop —
  real checkout, real `InitiateChargeJob`/`ChargeOrderService`, real webhook receiver/settlement — and fakes only
  the two hops that genuinely cross a process boundary: the outbound charge POST (`Http::fake`) and the inbound
  webhook (reconstructed with the exact raw-body HMAC that payment-service's `DeliverChargeResultJob` produces).
  payment-service's own charge/idempotency/signing logic is proven by **its** suite.
- Alternatives: (a) spin up both Laravel apps + both DBs in one test runner — rejected: they are separate services
  with separate databases, so a shared in-process runner would misrepresent the architecture and be brittle; (b) mock
  core-api internals too — rejected: that would test mocks, not the money path.
- Why: faking strictly at the wire keeps every core-api decision (locking, idempotency, ledger math, hold lifecycle)
  under test for real while still exercising the cross-service contract byte-for-byte, so the test proves the loop
  without falsely coupling two independently-deployed services.
- Trade-off: the seam between the two services is asserted by contract (matching headers/signature/payload), not by a
  live socket; a drift in the real wire contract is caught by each service's own contract tests, not this one.

### ADR-28: The hold-expiry sweep is write-guarded by status
- Decision: `TicketHoldRepository::releaseDueActiveHolds()` re-asserts `status = active` **in the bulk UPDATE
  itself**, not just in the prior SELECT, before flipping due holds to `released`.
- Alternatives: update by key alone (the snapshot already filtered to active) — rejected: it races a concurrent
  settlement.
- Why: between the SELECT of due holds and the UPDATE, a concurrent payment webhook (holding the order row lock) can
  legitimately convert a hold to `converted`. Without the status guard the sweep would clobber that committed
  conversion back to `released`, corrupting the hold lifecycle and detaching issued tickets from a live hold. The
  guard makes the write a no-op for any hold already converted. Extends ADR-07's read-time-expiry stance into the
  write path; covered by a dedicated regression test (`the expiry cron never corrupts a settled order`).
- Trade-off: none of substance — the guard only narrows the UPDATE; the happy path (still-active holds) is unchanged.

---

## Trade-offs made due to the 5-day constraint

- **Frontend is functional, not polished.** Just enough UI to show the data flow and the checkout/hold experience — basic styling, minimal client-side validation and error states. Backend correctness was the priority.
- **Tests focus on the money paths.** The required unit tests (order holds/expiry, concurrent oversell, payout calculation, inventory) are thorough; I did not aim for full coverage across every endpoint and service.
- **Nice-to-have features cut.** Ticket transfers, waitlist processing, per-vendor commission overrides, and QR-check-in polish are deferred — none affect money correctness.
- **Admin analytics kept basic.** Plain totals (sales, active events, vendor count) rather than charts or rich dashboards.
- **Notifications are fire-and-forget.** core-api enqueues notification jobs directly; if the process crashed between saving an order and enqueuing, a notification could be missed. Acceptable because notifications are not a money path, and the money flows are protected by idempotency and the hold-expiry safety net.


## With more time — what I'd improve, add, or redesign

- **Finish the deferred features:** ticket transfers and waitlist processing.
- **Integrate a real payment gateway and real email** in place of the simulators.
- **Dynamic roles/permissions (revisiting ADR-21):** adopt spatie/laravel-permission if roles become admin-defined or permissions become granular/per-user.
- **Multi-currency + FX and a tax/fee engine** (ADR-12) when expanding beyond Bangladesh — the schema already carries `currency` to make this a logic change, not a migration.
- **Refund-abuse scoring + auto-block:** track refund rate per attendee and flag/auto-block repeat abusers (e.g. buy→refund→rebuy cyclers) rather than relying only on per-refund ledger validation. Deferred from v1.
- **Broaden the tests** and add a concurrency/load test that hammers last-ticket checkout to prove no oversell under real parallel traffic.
- **Richer admin analytics and reporting** — per-event and per-vendor breakdowns.
- **Frontend polish:** proper form validation, loading/empty/error states, mobile layout, accessibility.
- **Configurable commission and refund policy** — per-vendor rates and per-event refund rules.
- **Basic operability:** health-check endpoints, log aggregation, and alerts on stuck pending orders and dead-lettered notifications.
- **CI/CD pipeline and proper secrets management** for real deployments.
