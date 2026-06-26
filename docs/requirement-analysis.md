# EventHub — Requirement Analysis

> **Graded deliverable (Rubric 1: Requirement Analysis & Product Thinking — 25%).**
> "Excellent" = edge cases *not* listed in the brief are identified (timezone conflicts, currency handling,
> concurrent checkout races, refund abuse), user stories cover all three stakeholders, the priority matrix shows
> pragmatic trade-offs, and risk analysis is specific and actionable.

## 1. Overview & scope

EventHub is a multi-vendor event ticketing and payout platform with three roles: **vendors** (organizers who create
events, sell tickets, and receive payouts), **attendees** (who browse, buy, and check in), and **platform admins**
(who approve vendors via KYC, set commission, and mediate disputes). The north star is **money correctness**: every
order, payment, refund, and payout must be auditable (append-only ledger), idempotent (no double-charge / double-pay),
and resilient to partial failure (a lost webhook or a mid-batch crash must never corrupt balances).

**In scope (5 days):** auth + roles + ownership; event & ticket-type lifecycle; checkout with a 15-minute hold and
distributed locking; ≥2 simulated payment gateways with idempotency and signed webhooks; refund policy + execution;
payout calculation (commission + threshold) with daily batch and vendor-requested cadence; the hold-expiry cron;
required unit tests (order processing, payout calc, inventory). **Out of scope (explicitly):** multi-currency,
ticket transfers, taxes/VAT, real payment gateways, named-seat selection, and polished UI. These are documented as
deferred in §6 so the cut is a decision, not an omission.

## 2. User stories by stakeholder

Written as `As a <role>, I want <capability> so that <value>`.

### Vendor
- As a vendor, I want to register and submit KYC details so that the platform can approve me before I sell tickets.
- As a vendor, I want to create, edit, publish, and cancel events (each with an IANA timezone) so that I control my
  event lifecycle and listings.
- As a vendor, I want to configure ticket types (general, VIP, early-bird, and fixed-size group bundles) with price,
  quantity, and sales window so that I can price and segment inventory.
- As a vendor, I want to see real-time sales and remaining inventory per event so that I can track performance.
- As a vendor, I want to register a webhook URL so that my own systems are notified of sales and payouts.
- As a vendor, I want to request a payout (or receive the daily batch) and see payout history/status so that I know
  when and how much I'm paid, net of commission.
- As a vendor, I want basic analytics (revenue, tickets sold, payout balance) so that I can report on an event.

### Attendee
- As an attendee, I want to browse and search published events so that I can find something to attend.
- As an attendee, I want to view event detail and select ticket types/quantities so that I can decide what to buy.
- As an attendee, I want to start checkout and get a **15-minute hold** on the inventory so that the tickets are
  reserved while I pay without being blocked by others.
- As an attendee, I want to pay and receive a confirmation with a QR-coded ticket so that I have proof of purchase.
- As an attendee, I want to view my order history and ticket status so that I can manage what I've bought.
- As an attendee, I want to check in at the event via QR so that entry is fast and fraud-resistant.
- As an attendee, I want to request a refund and have an in-policy refund applied automatically so that I'm not
  blocked on manual approval for clear-cut cases.
- As an attendee, I want to join a waitlist for a sold-out ticket type so that I'm offered a freed ticket if one
  becomes available.

### Platform Admin
- As an admin, I want to approve or reject vendor KYC so that only legitimate organizers can sell.
- As an admin, I want to set the platform commission (and, later, per-vendor overrides) so that the platform earns
  revenue.
- As an admin, I want to view platform-wide analytics (GMV, commission, payouts due) so that I can monitor health.
- As an admin, I want to mediate refund/payout **disputes** (out-of-policy refund contests) so that edge cases are
  resolved fairly.
- As an admin, I want to monitor cron/queue/webhook health so that money-moving jobs are observably succeeding.

## 3. Functional specifications

Concrete rules per module. Contracts are detailed in [`system-architecture.md`](./system-architecture.md).

- **Auth & roles.** Sanctum bearer tokens. Three roles (admin, vendor, attendee) with ownership boundaries — a
  vendor may only mutate its own events; an attendee may only view/refund its own orders. Role + ownership checked
  in policies, not controllers.
- **Vendors / KYC.** A vendor starts `pending`; an admin transitions to `verified` or `rejected`. Only a
  `verified` vendor may publish events or receive payouts.
- **Events.** Lifecycle `draft → published → ongoing → completed`, plus `cancelled` (terminal from any non-completed
  state). Each event stores `starts_at` (UTC) + an IANA `timezone`. Only `published` events are publicly listable.
  Cancelling an event with sold tickets triggers mass refunds (§5).
- **Ticket types.** Belong to an event; have name, price (integer poisha, BDT), total quantity, optional sales
  window, and a kind (general / VIP / early-bird). A **group bundle** is not a separate ticket type — it is a
  discount applied when a fixed group of **N units** of the same ticket type is bought together (e.g. buy 4 at a set
  discounted total); no partial bundles. Inventory still decrements by the N underlying units.
- **Orders / holds.** Checkout creates an order in `pending` with one hold per line item, reserving a **count** of
  inventory (not named seats) with `expires_at = now + 15 min`. Availability counts only non-expired holds —
  `available = total_quantity − quantity_sold − SUM(active holds with expires_at > now())` — evaluated **inside the
  hybrid lock + transaction** to prevent oversell (expiry enforced at read time; the `ReleaseExpiredHolds` cron is
  housekeeping, see §4). `quantity_sold` increments **only on payment success**. Re-POSTing checkout with the same
  idempotency key returns the same order.
- **Payments.** core-api calls payment-service with a shared secret + `Idempotency-Key`. A signed webhook callback
  flips the order to `paid`, increments `quantity_sold`, issues QR tickets, and writes a ledger entry.
- **Refunds.** Policy by time-to-event against the event's UTC instant: **>48h → 100%, 24–48h → 50%, <24h → 0%**.
  In-policy refunds are **auto-approved and executed**; out-of-policy contests open an admin-mediated **dispute**.
  Refunds reverse inventory only if the policy and business rules allow (see §5 abuse cases).
- **Payouts.** `net = sum(paid order revenue) − commission`, default commission **10%**. Payouts run as a **daily
  batch** and on **vendor request**. A vendor balance below the **5,000 BDT minimum threshold** rolls over to the
  next cycle. Payout execution is idempotent and per-vendor transactional (no double-pay on mid-batch crash).
- **Admin.** KYC decisions, commission config, dispute resolution, analytics, health monitoring.

## 4. Ambiguities & documented assumptions

For each: **Ambiguity → Assumption → Why.**

- **Currency.** Ambiguity: per-event or platform-wide currency? → **Single currency platform-wide: BDT, stored as
  integer minor units (poisha, 1 BDT = 100 poisha). Multi-currency is out of scope.** Why: the platform operates in
  Bangladesh; a single currency removes FX, rounding-at-conversion, and mixed-cart complexity, letting us guarantee
  exact integer money arithmetic — correctness over breadth.
- **Timezone.** Ambiguity: which tz governs "24h before event," reminders, and refund cutoffs? → **Store all
  datetimes in UTC; each event carries an IANA timezone; display in the event's tz; all windows (reminders, refund
  cutoffs, sales windows) are computed against the event's `starts_at` UTC instant.** Why: a single canonical instant
  avoids ambiguity across attendee/server timezones while still showing organizers and buyers local times.
- **Refund approval.** Ambiguity: auto-applied per policy, or always admin-mediated? → **Hybrid: in-policy refunds
  (100/50/0% by time-to-event) are auto-approved and executed; out-of-policy contests open a dispute an admin
  mediates.** Why: auto-handling clear cases is good UX and reduces admin load; disputes preserve human judgment for
  the genuinely contentious minority.
- **Group bundle pricing.** Ambiguity: per-bundle price vs % off N tickets; partial bundles allowed? → **Not a
  separate SKU — a discount applied to a fixed group of N units of the same ticket type bought together (e.g. 4 at a
  set discounted total); no partial bundles.** Why: modelling the bundle as a discount over the underlying units
  (matching the ERD) keeps one inventory counter per ticket type and avoids a parallel SKU to reconcile, while still
  decrementing the correct N units.
- **Commission rate snapshotting.** Ambiguity: which commission rate applies if the platform rate changes after a
  sale? → **The applicable commission rate is snapshotted onto each order (and its ledger entries) at sale time;
  payouts compute against that immutable historical rate, not the current platform rate.** Why: a vendor must be paid
  the terms in force when the ticket sold — recomputing past payouts against a later rate change would be incorrect
  and unauditable; the snapshot makes payout math reproducible from the ledger alone.
- **Event capacity ceiling.** Ambiguity: is there a hard cap on an event's size independent of how it's split into
  ticket types? → **Yes — `event.capacity` is a hard ceiling; the sum of its ticket types' inventory must not exceed
  it (`SUM(ticket_types.quantity_total) ≤ capacity`), enforced on ticket-type create/edit.** Why: capacity models the
  physical venue/limit, while ticket types merely slice it into tiers; enforcing the sum stops a vendor from
  overselling the room by adding or enlarging tiers.
- **Event lifecycle transitions.** Ambiguity: who advances an event through its lifecycle? → **`published → ongoing →
  completed` is driven automatically by a scheduled command off `starts_at`/`ends_at`; `cancelled` is a manual
  vendor/admin action.** Why: time-based transitions shouldn't wait on someone clicking, but cancellation is a
  deliberate decision (it triggers mass refunds), so it stays explicit and manual.
- **Hold vs payment / inventory decrement.** Ambiguity: does a hold reserve named tickets or a count; when is
  inventory decremented; when does an expired hold free up? → **A hold reserves a count, not named seats;
  availability counts only *non-expired* holds — `available = total − quantity_sold − SUM(holds WHERE status=active
  AND expires_at > now())`; `quantity_sold` increments only on payment success.** Why: counting expiry **at read
  time** means a hold never blocks stock past its 15-minute life even if cleanup hasn't run; the `ReleaseExpiredHolds`
  cron (every 5 min) is **housekeeping** (tidies stale rows, simplifies waitlist logic), not the thing that frees
  inventory — so correctness never depends on the cron's cadence.
- **Waitlist.** Ambiguity: per event or per ticket type; offer window? → **Per ticket type; a freed ticket is
  offered to the next person with a 30-minute claim window; nice-to-have.** Why: ticket-type granularity matches how
  scarcity actually occurs (VIP sells out, general doesn't); a bounded claim window keeps the queue moving.
- **Payout cadence + threshold.** Ambiguity: daily batch, on-request, or both; threshold value? → **Both daily
  batch and vendor-requested; default commission 10%; minimum payout 5,000 BDT, below which the balance rolls to the
  next cycle.** Why: a batch guarantees regular settlement while on-request serves urgent cashflow; a minimum
  threshold avoids dust payouts whose transaction cost exceeds their value.
- **Settling revenue (only after the event happens).** Ambiguity: when is vendor revenue paid out, given it may
  still be refunded? → **Revenue is settled only *after the event is marked `completed`* — never before; each payout
  reserves a refundable amount (`reserved_refund`) against not-yet-settled orders, and a negative `clawback` ledger
  entry is the fallback only for the residual case where already-settled revenue is later refunded (e.g. a post-event
  dispute override).** Why: settling only after the event has actually happened means a cancelled or no-show event
  never produces a paid-then-clawed-back vendor (the money simply was never paid), so clawback (and a transiently
  negative vendor balance) becomes the rare exception rather than the norm — see ADR-20.
- **Vendor-cancelled event.** Ambiguity: who bears the cost when a vendor cancels, and does the platform keep its
  fee? → **A vendor/admin cancellation refunds every attendee 100% (policy-overridden), funded by debiting the
  vendor (a negative `clawback` ledger entry), and the platform refunds its own commission too — it earns nothing on
  a cancelled event.** Why: the vendor caused the cancellation, so they bear the cost, not the attendee or the
  platform; refunding our commission is the fair, trust-preserving choice and keeps the ledger symmetric (sale +
  full reversal net to zero). Because revenue settles only after completion, the vendor usually hasn't been paid yet,
  so the debit nets before any real money left the platform — see ADR-23.
- **Refund request shape (no attendee-named amount).** Ambiguity: does the attendee specify a refund amount? → **No —
  a refund is requested against an order (optionally a subset of its tickets/items); core-api auto-derives the amount
  as `policy% × selected line totals` (100/50/0% by time-to-event). "Partial" means a subset of tickets, not an
  arbitrary sum.** Why: keeping the money math server-side makes it untamperable (a client can't request an arbitrary
  value) and consistent with the time-based policy; the payment-service merely executes the computed amount.
- **Seat selection.** Ambiguity: are seats named/assigned, or sold by count? → **Ticket *types* (VIP / general /
  early-bird) are sold per type by count; there is no seat map and no named/assigned seat selection — explicitly out
  of scope.** Why: count-based inventory is sufficient for the money-correctness core and avoids a seat-map data
  model and its allocation/locking complexity, which add no marks here.
- **Ticket transfers.** Ambiguity: in scope? → **Out of scope (deferred).** Why: transfers add ownership-change and
  re-issuance complexity that does not advance the money-correctness core; cut first under time pressure.
- **Multi-currency payouts, taxes/fees.** → **Explicitly out of scope.** Why: single-currency BDT only; tax handling
  is a jurisdiction-specific feature beyond the assessment's money-correctness focus.

## 5. Edge cases (the differentiator)

For each: scenario → risk → how the design handles it.

- **Concurrent checkout race (last ticket).** Two attendees check out the final ticket simultaneously. Risk:
  oversell. Handling: a **distributed lock** (per ticket-type key) wraps a **check-and-decrement inside a DB
  transaction** — availability is read and the hold created atomically; the loser sees "sold out." Covered by a
  required concurrency test.
- **Oversell via overlapping holds + paid orders.** Active holds plus paid orders could exceed stock if computed
  loosely. Risk: oversell. Handling: `available = total − quantity_sold − active_holds`, evaluated inside the lock;
  holds count against availability even before payment.
- **Timezone conflict.** Event in Dhaka, attendee in another tz, server in UTC. Risk: refund cutoff, reminders, and
  sales windows computed against the wrong clock → wrong refund %, mis-timed reminders. Handling: a single canonical
  UTC instant (`starts_at`) drives all window math; the event's IANA tz is for display only.
- **Reminder timing imprecision.** The reminder cron runs hourly, so a "24h before" reminder actually fires within
  **±1 hour** of the mark. Risk: a slightly-early/late reminder, or (if mishandled) a double-send. Handling: accepted
  simplification — ±1hr is immaterial for a courtesy reminder — and the unique `event_reminders(event_id, type)` row
  guarantees it's sent **once only** regardless of how many times the cron re-scans.
- **Currency rounding.** Percentage commission (10%) and partial refunds (50%) on odd amounts produce fractional
  poisha. Risk: balances that don't reconcile; float drift. Handling: all money is **integer poisha, no float**; a
  documented rounding rule (round half-up to the nearest poisha) is applied once at calculation, and the ledger is
  the source of truth so rounding is auditable.
- **Refund abuse.** (a) buy → refund → rebuy cycling to lock inventory; (b) refund after check-in; (c) partial-
  refund stacking beyond the original charge. Risk: inventory denial-of-service and over-refunding. Handling: refunds
  validated against the **ledger** (cumulative refunded ≤ charged); refund blocked once a ticket is checked in;
  in-policy auto / out-of-policy → dispute; repeated cycling is flagged for admin review. **v1 stops at the
  flag-for-admin-review signal**; fuller abuse tooling (per-attendee refund-rate scoring + auto-block) is deferred to
  "with more time" in the decision log.
- **Cart-exhaustion abuse (hold hoarding).** A single buyer opens many concurrent holds to starve inventory and deny
  other attendees. Risk: a malicious or buggy client locks up stock without ever purchasing. Handling: cap the number
  of **concurrent active holds per attendee per `ticket_type`**; checkout attempts beyond the cap are rejected until
  existing holds convert or expire — and the 15-min hold expiry returns abandoned holds regardless, so the attack
  surface is bounded in both count and time.
- **Double-charge.** Client retry or webhook re-delivery. Risk: charging twice. Handling: **idempotency key** on
  every money call; the payment-service stores key→result and replays the original result on a duplicate.
- **Double-pay (payout batch crash mid-run).** Batch crashes after paying some vendors. Risk: re-running pays them
  again. Handling: **per-vendor transactional marking** + idempotency key per payout; already-settled vendors are
  skipped on rerun.
- **Refund after payout.** An attendee refunds a ticket whose revenue was already paid out to the vendor. Risk: the
  vendor has been paid for a sale that no longer exists; naive logic can't claw it back. Handling: this is **rare by
  design** — settlement is gated on the refund window (the §4 reserve model), so most refunds happen before the money
  is ever paid out. For the residual case (event cancelled, dispute override), the refund writes a **negative
  `clawback` ledger entry** that offsets the vendor's **next** payout cycle. The running vendor balance **may go
  negative** and is carried forward (reconciled) against future sales rather than reversing a completed payout; the
  ledger remains the auditable source of truth for the deficit.
- **Payment-service down / slow webhook.** Order stuck `pending`; webhook never arrives. Risk: inventory held
  forever, or a late webhook hitting an expired order. Handling: the **15-min hold expiry** safety net releases
  inventory; webhooks are idempotent and tolerate arriving against an expired/already-final order.
- **Notification queue backlog.** Email/webhook jobs pile up. Risk: blocking checkout. Handling: notifications are
  enqueued asynchronously (Redis/BullMQ) and never on the synchronous checkout path; backlog degrades delivery, not
  purchasing.
- **Event cancellation with sold tickets.** Vendor cancels a published, partly-sold event. Risk: attendees charged
  for a dead event. Handling: cancellation enqueues **mass refunds** (100%, policy-overridden) and notifications;
  inventory is irrelevant once cancelled.
- **Hold expiry at the payment-success boundary.** Hold expires the instant payment succeeds. Risk: paying for
  released inventory or losing a legitimately-paid sale. Handling: payment success re-validates the hold inside the
  lock; if expired but inventory is still available it is re-secured, otherwise the charge is refunded and the order
  fails cleanly — the ledger records the outcome either way.
- **Price change straddling a hold (early-bird cutoff).** An attendee starts checkout while early-bird pricing is
  active, but the early-bird sales window closes during the 15-minute hold. Risk: the buyer is charged a different
  price than was quoted, or a vendor price edit mid-hold changes the amount due. Handling: the **unit price is locked
  onto the order line at hold creation**; the hold (and any resulting payment) honours that captured price regardless
  of window cutoffs or later price edits, so the quoted price is the charged price.

## 6. Priority matrix (must-have vs nice-to-have)

Pragmatic trade-offs for a 5-day solo build.

| Capability | Priority | Rationale |
|---|---|---|
| Auth + roles + ownership | Must | Everything depends on it |
| Event + ticket-type CRUD | Must | Core data |
| Order/checkout + 15-min hold + locking | Must | The hardest, highest-value path; required by tests |
| Payment-service + idempotency + webhook | Must | Money correctness |
| Payout calc + commission (10%) + threshold (5,000 BDT) | Must | Required by tests |
| Refund policy (100/50/0%) + execution + dispute | Must | Core money path |
| Cron jobs (expiry, payout, reminders, reports, waitlist) | Must (expiry) / Should (rest) | Expiry is the inventory safety net |
| Notification service (queue + retry + DLQ) | Should | Demonstrates resilience |
| Frontend (3 role areas, functional) | Should | Demonstrates data flow |
| QR check-in | Should | Earns the attendee entry story, but the money core scores higher; cut before any Must |
| Per-vendor commission override | Nice-to-have | Default 10% covers the requirement; overrides are an enhancement |
| Ticket transfer | Nice-to-have | Brief marks it optional; deferred (§4) |
| Waitlist | Nice-to-have | Per-ticket-type with a 30-min claim window; valuable but not money-critical |

**Cut order under time pressure (first to go):** ticket transfers → waitlist → per-vendor commission overrides →
QR check-in → frontend polish. None of these touch money correctness, which is non-negotiable.

## 7. Risk analysis

For each: likelihood/impact, what could go wrong, mitigation, and what to flag to a PM.

- **Concurrent-checkout oversell / locking correctness** — *Likelihood: high · Impact: high.* The hardest code; a
  weak lock or check-outside-transaction oversells the last tickets. Mitigation: distributed lock + check-and-
  decrement inside a transaction + a dedicated concurrency test simulating parallel last-ticket checkouts.
- **Cross-service consistency on a lost/duplicated webhook** — *Likelihood: medium · Impact: high.* Payment succeeds
  but the callback is lost or delivered twice → order stuck pending or double-processed. Mitigation: idempotency keys,
  signed webhooks tolerant of replay/out-of-order, and the hold-expiry safety net for the stuck case.
- **Payout double-pay on mid-batch crash** — *Likelihood: medium · Impact: high.* A crash mid-batch reruns and pays
  settled vendors again. Mitigation: per-vendor transactional marking + per-payout idempotency key; rerun skips
  settled vendors.
- **Refund after payout (clawback / negative balance)** — *Likelihood: medium · Impact: high.* Revenue already paid
  out is later refunded, leaving the vendor over-paid; attempting to reverse a completed payout corrupts settlement.
  Mitigation: model it as a **negative ledger entry that offsets the next payout cycle**, allow the vendor balance to
  go negative, carry the deficit forward, and reconcile against future sales — never reverse a settled payout in
  place. Flag to a PM: confirm the business is comfortable carrying negative vendor balances vs pursuing active
  clawback.
- **Scope creep in a 5-day solo build** — *Likelihood: high · Impact: medium.* Mitigation: the §6 priority matrix
  and a pre-committed cut order; nice-to-haves go first.
- **Frontend polish stealing time from backend correctness** — *Likelihood: medium · Impact: medium.* Mitigation:
  functional-only UI; correctness and tests are the graded core.
- **Money rounding / currency bugs** — *Likelihood: medium · Impact: high.* Mitigation: integer poisha, no float, a
  single documented rounding rule, and reconciliation against the append-only ledger in tests.

**Flag to a PM before starting:** (1) **refund ownership** — confirm the hybrid auto-vs-dispute model and the
100/50/0% thresholds; (2) **currency scope** — confirm single-currency BDT and that multi-currency is genuinely out;
(3) **payout threshold & commission values** — confirm 10% default commission and the 5,000 BDT minimum payout
threshold, since both directly affect vendor cashflow and platform revenue.
