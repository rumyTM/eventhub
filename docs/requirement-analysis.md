# EventHub — Requirement Analysis

> **Graded deliverable (Rubric 1: Requirement Analysis & Product Thinking — 25%).**
> "Excellent" = edge cases *not* listed in the brief are identified (timezone conflicts, currency handling,
> concurrent checkout races, refund abuse), user stories cover all three stakeholders, the priority matrix shows
> pragmatic trade-offs, and risk analysis is specific and actionable. Fill every `<!-- FILL -->` and delete the
> guidance comments before submitting. Keep it concrete — reviewers can tell generic from real.

## 1. Overview & scope
<!-- FILL: 2–3 sentences. What EventHub is, the three roles, and the "money must be auditable/idempotent/
resilient" north star. State explicitly what you are and are NOT building in 5 days. -->

## 2. User stories by stakeholder
Write as `As a <role>, I want <capability> so that <value>`, grouped. Cover **all three** roles — partial coverage
caps this section at "Good".

### Vendor
<!-- FILL: register/KYC; create/edit/publish/cancel events; configure ticket types (early bird/VIP/general/group);
view sales per event; register a webhook URL; request payouts; see payout history/status; basic analytics. -->

### Attendee
<!-- FILL: browse/search events; view event detail + ticket selection; checkout with a 15-min hold; pay; receive
confirmation; view order history; QR check-in; request a refund; join a waitlist. -->

### Platform Admin
<!-- FILL: approve/reject vendors (KYC); set platform + per-vendor commission; view platform analytics; mediate
disputes/refunds; monitor health. -->

## 3. Functional specifications
<!-- FILL: per module (Events, Ticket types, Orders/holds, Attendees, Vendors/KYC, Payouts, Refunds, Admin, Auth)
list the concrete rules: event lifecycle transitions, hold = 15 min, oversell prevention, commission formula,
minimum payout threshold, refund policy (100/50/0% by time-to-event), role/ownership boundaries. Reference
docs/system-architecture.md for contracts. -->

## 4. Ambiguities & documented assumptions
The brief says: *"If you have questions, document your interpretation and move forward."* This section is where you
earn marks for handling ambiguity. For each, state **Ambiguity → Assumption → Why**.

<!-- FILL at least these, plus your own:
- Currency: single currency platform-wide? Per-event? Assumption + rationale. How stored (minor units + ISO code).
- Timezone: events store UTC + IANA tz; "24h before event" and reminder windows computed in which tz? Assumption.
- Refund approval: auto-applied per policy, or always admin-mediated via a dispute? State your choice.
- Group bundle discount: how computed (per-bundle price vs % off N tickets)? Partial bundle allowed?
- Hold vs payment: does a hold reserve named tickets or just count? When is inventory decremented (hold vs paid)?
- Waitlist: per event or per ticket type? Hold offered to waitlisted user for how long?
- Payout cadence: daily batch vs vendor-requested vs both? Threshold currency.
- Ticket transfer (nice-to-have): in or out of scope?
- Multi-currency payouts, taxes/fees: explicitly out of scope? Say so. -->

## 5. Edge cases (the differentiator)
Reviewers explicitly reward edge cases beyond the brief. For each: the scenario, the risk, and how the design handles
it (link to the architecture/decision doc).

<!-- FILL — at minimum:
- Concurrent checkout race: two attendees, last ticket. -> distributed lock + check-inside-transaction.
- Oversell via overlapping holds + paid orders. -> available = total - sold - active_holds, inside lock.
- Timezone conflict: event tz vs attendee tz vs server tz for reminders, sales windows, refund cutoff.
- Currency handling: float rounding, mixed currencies in a cart, payout currency.
- Refund abuse: buy -> refund -> rebuy cycling; refund after check-in; partial-refund stacking beyond charge.
- Double-charge: client ret/webhook re-delivery -> idempotency key.
- Double-pay: payout batch crash mid-run -> per-vendor transactional marking.
- Payment-service down / slow webhook: order stuck pending -> expiry safety net + retry.
- Notification queue backlog: must not block checkout.
- Event cancellation with sold tickets -> mass refund + notify.
- Hold expiry exactly at payment success boundary (race). -->

## 6. Priority matrix (must-have vs nice-to-have)
Pragmatic trade-offs for a 5-day solo build. Be honest about what you'd cut.

| Capability | Priority | Rationale |
|---|---|---|
| Auth + roles + ownership | Must | Everything depends on it |
| Event + ticket-type CRUD | Must | Core data |
| Order/checkout + 15-min hold + locking | Must | The hardest, highest-value path; required by tests |
| Payment-service + idempotency + webhook | Must | Money correctness |
| Payout calc + commission + threshold | Must | Required by tests |
| Refund policy + execution | Must | Core money path |
| Cron jobs (expiry, payout, reminders, reports, waitlist) | Must (expiry) / Should (rest) | Expiry safety net is critical |
| Notification service (queue + retry + DLQ) | Should | Demonstrates resilience |
| Frontend (3 role areas, functional) | Should | Demonstrates data flow |
| QR check-in | Should | <!-- FILL --> |
| Ticket transfer | Nice-to-have | Brief marks it optional |
| Waitlist | Nice-to-have | <!-- FILL --> |
<!-- FILL: adjust, add rows, and justify each call. -->

## 7. Risk analysis
For each risk: likelihood/impact, what could go wrong, mitigation, and **what you'd flag to a PM before starting**.

<!-- FILL — specific and actionable:
- Distributed locking correctness (hardest part) — mitigation: lock + transaction + concurrency test.
- Inter-service consistency on partial failure (payment ok, webhook lost) — mitigation: idempotency + retry + expiry.
- Scope creep in 5 days solo — mitigation: priority matrix, cut nice-to-haves first.
- Time spent on frontend polish vs backend correctness — mitigation: functional UI only.
- Money rounding/currency bugs — mitigation: integer minor units, no float, tests.
Flag to PM: refund-policy ownership (auto vs admin), currency scope, payout cadence, SLA for webhooks. -->
