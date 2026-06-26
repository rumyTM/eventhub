# EventHub — AI-Augmented Development Workflow

> How this project is built with an AI coding agent (Claude Code) while keeping the engineering judgment human. It is
> both the author's working playbook and a demonstration — per the assessment — of how a new developer would use the
> repo's `.claude/` skills and commands to contribute without reading the whole codebase.

## The collaboration model (read this first)

The AI is an **accelerator, not a ghostwriter**. The split that keeps quality high:

- **Human owns the judgment** — assumptions, which edge cases matter, priorities, trade-offs, and the "why" behind
  every decision. These are what the rubric grades and what you must defend in the video.
- **AI owns the leverage** — turning your decisions into structured prose and code, filling scaffolds, catching
  omissions, scaffolding endpoints/tests, and enforcing the conventions in the `CLAUDE.md` files.

The failure mode to avoid: typing "fill this doc" and accepting generic output. A reviewer hiring a Team Lead spots
generic AI filler instantly, and the rubric's "Weak" tier is literally *"reviewer has to guess what the candidate was
thinking."* So every document starts from **your** answers to a few questions, then the AI expands them.

### The loop for every unit of work
1. **Decide** — answer the thinking prompts (docs) or state the intent (code) in your own words.
2. **Draft** — give Claude Code the prompt; it produces the doc/code following the `CLAUDE.md` standards.
3. **Revise** — edit so it reflects your real decisions and voice; remove anything you can't defend.
4. **Review** — for planning docs, get a rubric review before committing; for code, run `/format-and-test` and the
   relevant reviewer subagent.
5. **Log** — `/update-worklog`.

---

## Setup (once)

```bash
cd C:\laragon\www\assessment
claude
```
Then in Claude Code:
```
Confirm the git repo here is initialized and .gitignore is active. Run `git status`; if .idea/ or any .env is tracked, untrack it with `git rm -r --cached`. Then make a first commit of the existing scaffolding using a gitmoji message (see CLAUDE.md §10), e.g. `:tada: Initialize EventHub monorepo scaffolding`.
```

---

## DAY 1 — Planning documents (65% of the grade)

Do the docs in this order; each feeds the next: **requirement-analysis → erd → system-architecture →
technical-decision-log → development-plan**. For each, answer the thinking prompts first (in your own words, even
rough), then paste the draft prompt with your answers included.

Run once to see the day's checklist (read-only, changes nothing):
```
/day-plan 1
```

### 1a. Requirement Analysis
**Answer these first (your judgment — these resolve the ambiguities the brief leaves open):**
- Currency: one currency platform-wide (e.g. BDT), or per-event / multi-currency? How stored?
- Timezone: events store UTC + an IANA tz — which timezone governs "24h before event" for reminders and the refund
  cutoff (the event's tz)?
- Refunds: auto-applied strictly by the 100/50/0% policy, always admin-mediated via a dispute, or hybrid (policy
  auto-approves, disputes handle exceptions)?
- Group bundle: a fixed bundle price, or % off when buying N? Is a partial bundle allowed?
- Inventory: does a hold reserve a count or named tickets, and when is `quantity_sold` incremented — at hold or at
  paid?
- Waitlist: per event or per ticket type? How long is a freed ticket offered before moving to the next person?
- Payouts: daily batch, vendor-requested, or both? What's the minimum threshold (amount + currency)?
- Ticket transfers: in scope or explicitly deferred?
- The 3 risks you're personally most worried about, and what you'd flag to a PM before starting.
- If you run out of time, the first 3 things you'd cut.

**Then prompt:**
```
Let's do Day 1. Fill docs/requirement-analysis.md by replacing every <!-- FILL --> with real EventHub content. Use MY decisions below — don't invent defaults:
- Currency: <your answer>
- Timezone handling: <your answer>
- Refund approval model: <your answer>
- Group bundle pricing: <your answer>
- Inventory/hold decrement: <your answer>
- Waitlist scope: <your answer>
- Payout cadence + threshold: <your answer>
- Ticket transfers: <in/out>
- Top risks + PM flags: <your answers>
- First things to cut under time pressure: <your answers>
Cover user stories for all three roles, document each assumption with its rationale, and include edge cases beyond the brief (timezone conflicts, currency rounding, concurrent-checkout race, refund abuse). Keep it specific. Stop when done so I can review.
```

### 1b. ERD
**Decide first:** any tables/columns to add or change versus the seeded ERD, given your assumptions above (e.g. a
`currency` column placement, a `reminded_at` flag, waitlist granularity).
```
Now refine docs/erd.md. The seeded Mermaid ERD reflects the architecture; adjust it to match the assumptions we just locked in requirement-analysis.md (<note any changes>). Then fill the relationship notes, normalization/denormalization rationale, indexing strategy (name each index and the query it serves), the financial audit-trail approach, and the soft-delete vs hard-delete policy. Stop when done.
```

### 1c. System Architecture
**Decide first:** locking mechanism (Redis lock vs DB `lockForUpdate`) and why; how you handle payment-service being
down; what happens when the notification queue backs up.
```
Now fill docs/system-architecture.md. Keep the seeded diagrams and the 4 API-contract skeletons in sync with our decisions. Use MY calls: locking = <Redis lock | DB row lock> because <reason>; partial-failure behavior = <your answers for payment-service down, lost/duplicate webhook, queue backlog, payout-batch crash>. Expand service-boundary justification, the auth strategy, the background-job table, and the resilience section. Stop when done.
```

### 1d. Technical Decision Log
**Decide first:** the "why" and trade-off for the big calls — locking choice, money as integer minor units,
refund ownership, Redis-for-queue-and-locks, monorepo. (ADR-01..10 are pre-seeded; you supply the reasoning.)
```
Now fill docs/technical-decision-log.md. For each pre-seeded ADR, write the real "why" and the trade-off accepted under the 5-day constraint, in my voice — here's my reasoning for the key ones: locking <...>, money representation <...>, refund ownership <...>, Redis choice <...>, monorepo <...>. Add any ADRs we made that aren't listed. Write a substantive "with more time" section. Avoid "because it's popular" — articulate trade-offs. Stop when done.
```

### 1e. Development Plan
```
Now fill docs/development-plan.md: the phasing narrative (what you tackled first and why), the critical path, and a realistic 3–4 dev / 2-week team-delegation plan with parallel streams, dependencies, integration checkpoints, and onboarding via the CLAUDE.md files. Stop when done.
```

### After each doc
Bring it back for a rubric review **before committing**:
> In your Claude desktop chat (this assistant): *"review docs/requirement-analysis.md"* — I'll score each section
> Weak/Good/Excellent and point out what's still generic. Revise, then commit:
```
/update-worklog
```
(then `git add` + commit that doc with a gitmoji, e.g. `:memo: Fill requirement-analysis assumptions and edge cases`)

---

## DAY 2 — Scaffold + schema + core CRUD
```
/day-plan 2
```
```
Let's do Day 2 from PLAN.md. Run /scaffold-service core-api then /scaffold-service payment-service (copy the canonical stubs, register AssignLogTraceId in bootstrap/app.php). Bring up docker-compose with mysql + redis + both Laravel services and confirm health. Then create migrations for every entity in docs/erd.md, the enums (/add-enum ...), Sanctum auth with a role enum + EnsureRole middleware, and CRUD for Event and TicketType with ownership + lifecycle rules. Run /format-and-test and /update-worklog.
```

## DAY 3 — Orders, locking, payments, financial tests (highest risk)
```
/day-plan 3
```
```
Let's do Day 3 from PLAN.md. Build the checkout flow: create order + 15-min holds with a distributed lock and check-inside-transaction so inventory can't oversell. Build payment-service gateways (StripeSim/PayPalSim, configurable rates), the /payments endpoint with idempotency, the core-api -> payment client (shared secret + Idempotency-Key + trace header) in a queued job, and the signed webhook callback that issues tickets + writes a ledger entry. Add refund execution and payout execution. Write the required unit tests: hold/expiry, concurrent oversell, idempotent checkout, payment idempotency, payout calc, inventory. Then run the financial-logic-reviewer agent, fix findings, /format-and-test, /update-worklog.
```

## DAY 4 — Notifications, cron, frontend
```
/day-plan 4
```
```
Let's do Day 4 from PLAN.md. Run /scaffold-service notification-service: BullMQ queues, the email (simulated) and vendor-webhook jobs, exponential backoff (1/4/16/64s, max 5), dead-letter, delivery tracking, and reading trace_id from the payload. Wire core-api's NotificationPublisherContract to publish jobs (include trace_id). Build the remaining cron jobs (ProcessPayoutBatch with no-double-pay, SendEventReminders, GenerateSalesReport, ProcessWaitlist). Then /scaffold-service frontend and build the vendor dashboard, attendee pages + checkout with a hold countdown, and the admin panel. /format-and-test, /update-worklog.
```

## DAY 5 — Tests, docs, seed data, video
```
/day-plan 5
```
```
Let's do Day 5 from PLAN.md. Broaden tests and make all required suites pass. Create a seeder with realistic demo data (vendors, events, ticket types, orders, payouts) using clearly-fake credentials. Generate API docs (Postman collection or OpenAPI) covering every endpoint with request/response examples. Do a final pass on all docs, the README, and the CLAUDE.md files, and verify the project runs from a clean clone via docker compose. /update-worklog.
```
Then record the 15–20 min video (architecture → 2–3 key decisions → live demo: create event, buy ticket, payout, refund → AI workflow → retrospective) and paste the link into the README.

---

## Safety habits (every session)
- `/format-and-test` before you consider any code "done".
- Run `financial-logic-reviewer` after touching any order/payment/refund/payout/inventory code.
- `/update-worklog` at the end of every session — it keeps WORKLOG.md current so you (and the AI) can resume cleanly.
- Commit per logical unit, not one giant commit, and start every commit message with a **gitmoji** (CLAUDE.md §10) —
  e.g. `:sparkles:`, `:white_check_mark:`, `:card_file_box:`, `:lock:`. Never commit `.env` or secrets (`.gitignore` protects you).
- If the AI proposes something that violates a `CLAUDE.md` rule, push back — the standards win.

## How a new developer uses this repo (the 30-minute path)
1. Read the root `CLAUDE.md` (system map, how services talk, how to run).
2. Read the `CLAUDE.md` of the one service they'll touch.
3. Use the scoped skill (`backend-core-api`, `payment-service`, `notification-service`, `frontend`) — it points to the
   key files, patterns, and how to run that service's tests.
4. Scaffold with a slash command (`/make-endpoint`, `/crud`, `/add-enum`, `/add-resource`) instead of hand-writing.
5. Review with `laravel-code-reviewer` / `financial-logic-reviewer`, finish with `/format-and-test` + `/update-worklog`.
They never need to read the whole codebase — the service boundary + its CLAUDE.md + its skill are enough to contribute.
