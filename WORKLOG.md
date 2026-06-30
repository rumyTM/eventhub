# EventHub — Work Log

> Running, most-recent-first log of what changed, decisions made, verification, and what's next. Update at the end of
> every working session with `/update-worklog`. Significant decisions get promoted into
> [`docs/technical-decision-log.md`](./docs/technical-decision-log.md).

---

## 2026-06-30 — Day 5 (Patch): Refund inventory accounting fix + payout panel bugs + test regression lock-in
**Maps to:** PLAN.md Day 5 — hardening & verification. `services/core-api/CLAUDE.md` §F/§I.

**What changed (`services/core-api`)**
- **`TicketTypeRepositoryInterface`** — added `decrementSold(string $id, int $by): void` contract.
- **`TicketTypeRepository`** — implemented `decrementSold` using plain `decrement()` (cross-database; no `GREATEST()` — see decision below).
- **`ProcessRefundWebhookService::voidTicketsAndUpdateOrder()`** — rewrote to always (1) void all valid tickets, (2) call `decrementSold` per order item regardless of money-back percentage. Order status (`refunded` vs `partially_refunded`) is money-based only. Implements ADR-37.
- **`PayoutRepository::list()`** — added `->with('vendor:id,business_name')` eager load so the payout admin panel can show the vendor business name without an N+1.
- **`PayoutResource`** — added `'vendor' => $this->whenLoaded('vendor', ...)` returning `{ business_name }`.

**What changed (`frontend`)**
- **`lib/api/types.ts`** — added `vendor?: { business_name: string }` to `Payout` interface; corrected `build()` return type to `{ batch_id: string; count: number; payouts: Payout[] }`.
- **`lib/api/payouts.ts`** — fixed `build()` return type (was `{ payouts_created: number }`).
- **`app/(admin)/admin/payouts/page.tsx`** — fixed vendor column (`p.vendor_id.slice(-8)` → `p.vendor?.business_name ?? p.vendor_id`); fixed toast (`res.payouts_created` → `res.count`).

**Tests updated (`services/core-api/tests/`)**
- **`RefundWebhookTest`** — added `quantity_sold = 0` assertion to the 100% refund path; renamed and rewrote the partial-refund test to assert tickets are voided and inventory returned (was asserting tickets remained valid — wrong per ADR-37).
- **`RefundLoopEndToEndTest`** — added `quantity_sold = 0` assertion to the existing 100% e2e loop; added `test_fifty_percent_policy_refund_voids_tickets_returns_inventory_and_marks_partially_refunded` driving the full 24–48h window path (requestRefund → runRefundJob → deliverRefundWebhook) and asserting policy=50, amount=50 000, tickets refunded, `quantity_sold=0`, order `partially_refunded`, and −50k/+5k ledger net.

**Decisions**
- **`decrement()` over `DB::raw("GREATEST(0, ...)")`** — `GREATEST()` is MySQL-only; tests run on SQLite. Replay safety comes from the idempotency guard in `handle()` (`lockOpenForOrder` returns null on replay, skipping the entire settlement block), so `decrementSold` is never called twice for the same refund. The floor is redundant; `decrement()` is simpler and portable.
- **ADR-37 confirmed:** ticket fate (always voided) is independent of money-back percentage. The `partially_refunded` status is money-based only — not ticket-based.

**Verification**
- `php artisan test --filter=Refund` — **53 passed, 0 failed**.
- `php artisan test` (full suite) — **251 passed, 0 failed**.

**Finding (not fixed — flagged for follow-up)**
- `RefundService.php` lines 56–58 have **no guard against `checked_in` tickets**. A refund can currently be requested on an order where tickets are already consumed; `decrementSold` would return consumed seats to inventory incorrectly. ADR-37 follow-up #2 — add checked-in guard before calling `RefundService::request()`.

**Next**
- Add checked-in guard to `RefundService` (ADR-37 follow-up #2).
- Commit this patch: `:white_check_mark: Lock in refund inventory accounting + 50%-policy regression test` (pending explicit user approval).

---

## 2026-06-29 — Day 4 (Slice 4): Next.js 14 frontend — full three-role UI, typed API client, Dockerfile
**Maps to:** PLAN.md Day 4 — frontend build (attendee + vendor + admin areas). `frontend/CLAUDE.md` §B–§E.

**What changed (`frontend/`)**
- **Scaffolded** Next.js 14 App Router project (TypeScript, Tailwind CSS, shadcn/ui primitives hand-built from Radix UI). TanStack Query v5 for server state; Sonner for toasts; Lucide for icons.
- **`lib/api/`** — full typed API client layer:
  - `client.ts`: `apiFetch` fetch wrapper that attaches `Authorization: Bearer` token, parses the `{success,message,data,errors}` envelope, throws `ApiError` (with `status`, `errors`, `retryAfter`) on `success:false`. Token persisted to `localStorage`; token-in-memory cache for SSR safety.
  - `error.ts`: `ApiError` class with `isUnauthorized`, `isForbidden`, `isRateLimited` helpers.
  - `types.ts`: TypeScript types mirroring all core-api resource shapes (User, Event, TicketType, Order, Hold, Refund, Vendor, Payout, Pagination, EnumValue).
  - `auth.ts`, `events.ts`, `orders.ts`, `payouts.ts`, `admin.ts`: resource-level API modules.
- **`lib/auth-context.tsx`**: `AuthProvider` + `useAuth` hook; restores session from localStorage on mount via `GET /auth/me`; exposes `login`, `register`, `logout`.
- **`app/providers.tsx`**: `QueryClientProvider` (retry skips 401/403/404) + `AuthProvider` + Sonner `Toaster`.
- **`app/page.tsx`**: root redirect — bounces to `/admin`, `/vendor`, or `/events` by role, or `/login` if unauthenticated.
- **Auth area `(auth)/`**: login page, register page (with role selector), shared centered layout.
- **Vendor area `(vendor)/`** (role-guarded):
  - Dashboard with analytics (event count, tickets sold, gross revenue).
  - Events list (paginated, with delete).
  - Create event form (`/vendor/events/new`) and edit event form (`/vendor/events/[id]/edit`).
  - Event detail page with per-event sales table and `TicketTypesSection` (add/delete ticket types via dialog, price entry in BDT converted to poisha).
  - Payout history table with status badges and summary cards.
- **Attendee area `(attendee)/`** (role-guarded):
  - Event listing (card grid, filters to published/ongoing, sold-out detection).
  - Event detail + ticket selection (quantity stepper per ticket type, order summary, checkout initiates `POST /orders` with idempotency key).
  - Checkout page `(/checkout/[orderId])`: polls `GET /orders/{id}` every 3s, shows live countdown from `hold_expires_at`, handles `paid`/`failed`/`expired` terminal states.
  - Order history (paginated) + order detail with refund request dialog (maps `errors` back to form, explains policy in UI).
- **Admin area `(admin)/`** (role-guarded):
  - Overview dashboard (pending vendors count, GMV from payouts, pending payout count, quick-action links).
  - Vendor KYC approval queue: verify + reject (with reason dialog).
  - Payout management: build batch button + execute per-payout.
  - Dispute/refund queue: admin-initiated refund with reason.
- **Shared components**: `RoleGuard`, `Nav`, `LoadingSpinner`, `EmptyState`, `ErrorDisplay` (handles 401/403/429/generic with retry button).
- **UI primitives** (`components/ui/`): Button, Input, Label, Card, Badge, Select, Textarea, Dialog, Tabs, Toast (Sonner wrapper).
- **`Dockerfile`**: multi-stage (node:22-alpine builder → standalone runner); `output: "standalone"` in `next.config.mjs`; build arg `NEXT_PUBLIC_API_BASE_URL` for Docker networking.
- **`docker-compose.yml`**: updated `frontend` service to use build args for `NEXT_PUBLIC_API_BASE_URL` pointing at `core-api:8000`.

**Decisions**
- **Single `apiFetch` wrapper in `lib/api/client.ts`** — all UI code calls typed resource modules (`eventsApi`, `ordersApi`, etc.), never `fetch` directly. Enforces envelope parsing and `ApiError` uniformly; satisfies the frontend CLAUDE.md hard rule.
- **Token in `localStorage` (not httpOnly cookie)** — Next.js 14 App Router with static/standalone output cannot easily set httpOnly cookies from the client side without a route handler. `localStorage` is pragmatic for an assessment; a production build would use a `/api/session` server route. Noted as a trade-off.
- **TanStack Query retry skips 401/403/404** — these are deterministic failures; retrying wastes requests and delays the user seeing a redirect or error state.
- **Hold countdown uses `secondsUntil(expires_at)` + `setInterval(1s)`** — computed from the ISO-8601 timestamp returned by the API, not from a server-pushed clock, which is correct for the 15-min hold display requirement.
- **`npm run build` standalone output** — `output: "standalone"` enables the lean Docker runtime that copies only used dependencies into the runner stage, keeping the image small.

**Verification**
- `npm run build` — **17/17 pages compiled, 0 errors, 0 TypeScript errors** (Next.js type-checks during build).
- `npm run lint` — **✔ No ESLint warnings or errors**.
- No `npm run test` yet (msw-mocked integration tests not yet written — noted as next task per CLAUDE.md §D).

**Next**
- Write msw-mocked integration tests for checkout, event create, and vendor approval flows (CLAUDE.md §D).
- Commit frontend (pending explicit user approval) with `:sparkles: Add Next.js 14 frontend — vendor/attendee/admin areas, typed API client, Dockerfile`.
- Update `docs/technical-decision-log.md` with localStorage token ADR if promoted.

---

## 2026-06-30 — Day 4/5 (slice 3 · Chunk F): end-to-end loop tests (refund + payout) + financial-reviewer fixes + ADR-31–36
**Maps to:** Day 4–5 — payouts/refunds close-out (PLAN.md); core-api `CLAUDE.md` §F/§H/§I; ADR-09 (idempotency),
ADR-20 (clawback), ADR-27 (e2e tests fake only the process boundary). **Chunk F — CLOSE SLICE 3: wire + proof.
Full refund and payout loops driven end-to-end; idempotency confirmed under replay; financial-logic-reviewer ran;
3 findings fixed; 6 ADRs promoted.**

**What changed (`services/core-api`)**
- **`RefundLoopEndToEndTest`** (new — `tests/Feature/Payments/`) — 5 test cases:
  - Full success loop: paid order → attendee refund request (real endpoint) → `ExecuteRefundJob` (Http::fake
    payment-service) → signed refund webhook → refund completed, reversal + commission ledger written, tickets voided
    (`TicketStatus::Refunded`), order `refunded`, `SendRefundConfirmationJob` queued once.
  - Forced failure: webhook delivers `failed` → no ledger, no ticket change, order still `paid`.
  - Webhook replay: signed webhook delivered twice → exactly 2 ledger rows (not 4); notification queued once.
  - Job re-dispatch after settlement: `runRefundJob()` after webhook already settled → no second payment-service call.
  - **Clawback path (ADR-20/36, M-2 reviewer fix)**: paid payout + PayoutItem pre-created for the order → refund
    webhook → asserts `entry_type = 'clawback'` (not `'refund'`) in ledger, commission reversal still written.
- **`PayoutLoopEndToEndTest`** (new — `tests/Feature/Payouts/`) — 5 test cases:
  - Full success loop: vendor with eligible ledger balance → `PayoutBuildService::buildForVendor()` → execution
    (Http::fake) → signed payout webhook → payout `paid`, ONE negative `payout` ledger entry, `settled_at` set on
    `PayoutItem`, balance = 0, `SendPayoutNotificationJob` queued once.
  - Forced failure: webhook delivers `failed` → no ledger, balance unchanged (still 90k).
  - Webhook replay: webhook delivered twice → exactly 1 payout ledger row; balance stays 0.
  - Execution retry: `execute()` called twice (both send `payout-exec:{id}` key) → one ledger entry after webhook.
  - Build idempotency: `buildForVendor` same batch/vendor twice → returns the same Payout, count = 1.
- **`ExecuteRefundJob`** — added `$permanentlyFailed` flag (H-1 reviewer fix); `handle()` sets it on 4xx before
  calling `markExecutionFailed + fail()`; `failed()` skips `markExecutionFailed` when flag is set. Mirrors the payout
  job's pattern exactly.
- **`PayoutRepository::orderSettledPaidForVendor`** — added `->lockForUpdate()` before `->exists()` (H-2 reviewer
  fix); participates in the refund webhook's `DB::transaction` to serialize clawback-vs-refund classification against
  a concurrent payout webhook.
- **`docs/technical-decision-log.md`** — ADR-31–36 added:
  ADR-31 (`payout_ref` = Payout.id), ADR-32 (status vocab mismatch), ADR-33 (`settled_at` on payout_items),
  ADR-34 (`restrictOnDelete` on financial FKs), ADR-35 (`$permanentlyFailed` flag), ADR-36 (`lockForUpdate` for
  clawback classification race).

**Decisions**
- **`$permanentlyFailed` flag in execution jobs (H-1)** — the `failed()` callback is intended for "retries exhausted";
  relying on a downstream terminal-state guard for the 4xx double-call was fragile. The explicit flag in the job layer
  is the payout job's documented pattern; now consistent across both jobs. (→ ADR-35)
- **`lockForUpdate` on `orderSettledPaidForVendor` (H-2)** — the `Clawback` vs `Refund` ledger entry type is the
  signal for funds-recovery; a misclassification silently drops a vendor liability from the reconciliation queue.
  A `lockForUpdate` inside the existing refund-webhook transaction is the correct serialization point. (→ ADR-36)
- **Clawback e2e test added (M-2)** — the ADR-20 clawback path (refund after paid payout) was tested only at the
  webhook-unit level (RefundWebhookTest). The e2e test confirms it works end-to-end with the full real-code path.
- **`RefundLoopEndToEndTest` uses real refund-request endpoint** — authenticates as attendee via `Sanctum::actingAs`,
  exercises the real policy check (>48h → 100%), discovers the refund from DB; consistent with ADR-27 (fake only the
  true process boundary).
- All decisions promoted to `docs/technical-decision-log.md` as ADR-31–36.

**Financial-logic-reviewer findings (Chunk F)**
- **H-1 (fixed)**: `ExecuteRefundJob::failed()` double-called `markExecutionFailed` on 4xx; absorbed by downstream
  guard but fragile. Fixed with `$permanentlyFailed` flag.
- **H-2 (fixed)**: `orderSettledPaidForVendor` unlocked read inside webhook transaction — clawback-vs-refund race.
  Fixed with `lockForUpdate()`.
- **M-2 (fixed)**: clawback path uncovered by e2e tests. Fixed with the new
  `test_refund_after_paid_payout_writes_clawback_ledger_not_a_refund_reversal` test.
- **M-3/L-1/L-2/L-3/N-1/N-2/N-3**: documented in ADRs or noted as acceptable trade-offs; no code change needed.
- **Critical**: none found. No path unconditionally double-charges, double-pays, or oversells.

**Verification**
- `composer format` (Pint) clean — auto-removed one unused import from `PayoutLoopEndToEndTest`; no other changes.
- core-api: **210 passed (791 assertions)**, all suites green, zero regressions (up from 200 before Chunk F).
- payment-service: unchanged (61 passed / 303 assertions from Chunk E).

**Next**
- Commit Chunks E + F (pending explicit user approval) with gitmoji message below.
- Proceed to Slice 4 (frontend + admin panel) or continue with remaining backend features per PLAN.md.

---

## 2026-06-30 — Day 4 (slice 3 · Chunk E): payout EXECUTION loop — payment-service endpoint + core-api webhook + tests
**Maps to:** Day 4 — payouts (PLAN.md); payment-service `CLAUDE.md` §A/§C/§D/§G; core-api `CLAUDE.md` §F/§H;
ADR-09 (idempotency), ADR-10 (inter-service auth: shared-secret bearer + HMAC-SHA256 body signature), ADR-13 (vendor
balance = SUM ledger; payout entry is NEGATIVE). **Chunk E — EXECUTE: money moves; payout lifecycle driven to `paid`
or `failed` via signed webhook; ledger written only on success.**

**What changed (`services/payment-service`)**
- **`PayoutStatus` enum** (`app/Enums/`) — `Pending/Completed/Failed`; `isTerminal()` predicate.
- **`payouts` migration** — `ulid id`, `payout_ref` (echo of core-api Payout ID), `vendor_id`, `amount`, `currency`
  (BDT default), `status`, `gateway_ref` (nullable), `idempotency_key` (unique), index on `payout_ref`.
- **`alter_transactions_add_payout_id_nullable_payment` migration** — makes `payment_id` nullable (payout-type rows
  have no associated charge); adds `payout_id` FK; MySQL FK drop/re-add + SQLite `->change()`.
- **`Payout` model** — `HasUlids`, fillable, `status` cast to `PayoutStatus`, `amount` cast to integer.
- **`PayoutRepository`** (`Contracts/` + `Eloquent/`) — `create`, `findOrFail`, `findForUpdate` (row lock),
  `markResolved`.
- **`PayoutService`** — `createPayout(key, payload)`: idempotency fast-path → row-lock → unique-constraint catch;
  `persist()` stores payout + idempotency record; `resolve(payoutId)`: optimistic read → gateway.default().payout()
  OUTSIDE transaction → row-lock re-check → persist status + ONE ledger row (negative for success, 0 for failure).
- **`DeliverPayoutResultJob`** — `ShouldBeUnique`, 5 tries, backoff [10,30,60,120]; resolves payout FIRST then POSTs
  signed webhook to `CORE_API_PAYOUT_WEBHOOK_URL` with bearer + `X-Signature: hmac_sha256(body, secret)`.
- **`CreatePayoutRequest`** — header `Idempotency-Key` folded into validated data; 422 on missing key.
- **`PayoutResource`** — `{value,label}` status, integer amounts, no card data.
- **`PayoutController`** — `store()`: 201 + queued resolution.
- **Route** `POST /api/v1/payouts` behind `EnsureServiceToken + throttle:payouts`.
- **`AppServiceProvider`** — `payouts` rate limiter (60/min).
- **`config/services.php`** — `core_api.payout_callback_url`.
- **`.env.example`** — `CORE_API_PAYOUT_WEBHOOK_URL`.
- **`Transaction.$fillable`** — added `payout_id`.
- **lang** `payouts.created`, `payouts.idempotency_key_required`.
- **`PayoutFactory`** (payment-service) with `completed()`/`failed()` states.
- **Tests** `PayoutEndpointTest` (8 cases) + `PayoutResolutionTest` (4 cases).

**What changed (`services/core-api`)**
- **`PayoutResult`** value object (`app/Support/Payments/`).
- **`PaymentServiceContract::executePayout`** — new contract method.
- **`PaymentClient::executePayout`** — POSTs to `/api/v1/payouts` with bearer + `Idempotency-Key` +
  `LogHelper::traceHeaders()`; uses `services.payment.base_url`.
- **`PayoutExecutionService`** — `execute(payoutId)`: guards on terminal/vanished, flips `pending → processing` in
  row-locked transaction, calls `paymentService->executePayout`; 5xx re-throws for retry, 4xx calls
  `markExecutionFailed`. `markExecutionFailed(payoutId)`: row-locked, marks `failed` if not already terminal.
- **`ExecutePayoutJob`** — `ShouldBeUnique`, 5 tries, backoff [10,30,60,120]; 4xx → `markExecutionFailed + fail()`;
  5xx → rethrow; `failed()` callback also calls `markExecutionFailed`.
- **`ProcessPayoutWebhookService`** — single locked transaction; guard order: unknown → no-op, replay → no-op, amount
  mismatch → 422 (`PayoutWebhookMismatchException`). On `completed`: mark `paid`, mark items settled, write ONE
  NEGATIVE `payout` ledger entry. On `failed`: mark `failed`, no ledger. Post-commit: dispatch
  `SendPayoutNotificationJob` for both outcomes.
- **`PayoutWebhookController`** + **`PayoutWebhookRequest`** — validates `status.value` in `['completed','failed']`
  (payment-service vocabulary, NOT core-api enum values).
- **`PayoutWebhookMismatchException`** — extends `HttpException` with 422.
- **`SendPayoutNotificationJob`** — stub; logs payout_id + status (mirrors `SendRefundConfirmationJob`).
- **Admin `execute` endpoint** — `POST /api/v1/admin/payouts/{payout}/execute`; guards on `pending|approved`; returns
  200 + `ExecutePayoutJob` queued.
- **Webhook route** `POST /api/v1/internal/payments/payout-webhook` behind `webhook.signature` middleware.
- **`PayoutRepositoryInterface`** + **`PayoutRepository`** expanded: `find`, `findForUpdate`, `markProcessing`,
  `markPaid`, `markFailed`, `markItemsSettled`.
- **`PayoutFactory`** (core-api) with `paid()`/`failed()`/`processing()` states; **`PayoutItemFactory`**.
- **lang** `payouts.execution_queued`, `payouts.not_executable`, `payouts.webhook_processed`,
  `payouts.webhook_amount_mismatch`.
- **Tests** `ExecutePayoutJobTest` (7 cases) + `PayoutWebhookTest` (7 cases).

**Decisions**
- **Payment-service `payout_ref` = core-api Payout ID** — the webhook echoes it back unchanged so core-api can
  `findForUpdate($payload['payout_ref'])` by primary key; no secondary lookup table needed.
- **Status vocabulary mismatch is explicit** — payment-service sends `completed/failed`; core-api maps `completed →
  paid`. The boundary is `PayoutWebhookRequest` validation (uses string literals, not core-api enum values) +
  `ProcessPayoutWebhookService` (`=== 'failed'` check). This is intentional; mixing vocab in the enum would couple
  the two services.
- **`SendPayoutNotificationJob` dispatched on both success and failure** — the vendor needs to know either outcome;
  failure cases dispatch with `PayoutStatus::Failed->value` so the notification stub can differentiate.
- **`transactions.payment_id` nullable via new migration** — payout-type ledger rows have no associated charge; making
  it nullable in a separate migration preserves existing data and FK integrity.
- **`markExecutionFailed` called from both `handle()` (4xx branch) and `failed()` (exhausted retries)** — the
  row-lock + terminal guard inside the method makes double-calls safe (idempotent).

**Bugs fixed during test run**
- `PayoutService::persist()` was not passing `idempotency_key` to `Payout::create()` → `NOT NULL` constraint failure
  (fixed: added field to create array).
- `ExecutePayoutJobTest` overrode `services.payment.url` but `PaymentClient::endpoint()` reads
  `services.payment.base_url` → real HTTP attempted despite `Http::fake()` (fixed: corrected config key).
- `PayoutWebhookTest` failure-case assertion was `assertNotPushed(SendPayoutNotificationJob)` but the service
  correctly dispatches on failure too (fixed: assertion updated to `assertPushed(..., 1)`).

**Verification**
- `composer format` (Pint) clean — auto-fixed import ordering in 3 files + PayoutController brace style.
- payment-service: **61 passed (303 assertions)**, all suites green.
- core-api: **200 passed (708 assertions)**, all suites green, zero regressions.

**Next**
- Run `financial-logic-reviewer` on Chunk E money paths before committing.
- Commit Chunk D + E together (pending user approval).
- Day 5: notification-service wiring, frontend vendor dashboard, admin panel.

---

## 2026-06-30 — Day 4 (slice 3 · Chunk D): payout CALCULATION + batch build (core-api, decide-only)
**Maps to:** Day 4 — payouts (PLAN.md); core-api `CLAUDE.md` §F + §H; `docs/erd.md` (payouts, payout_items,
ledger_entries); ADR-08 (integer minor units), ADR-09 (idempotency), ADR-12 (single-currency), ADR-13 (vendor balance =
SUM ledger), ADR-14 (commission snapshot), ADR-20 (defer revenue until event completed). **Chunk D — DECIDE ONLY: builds
Payout + PayoutItem rows (status=pending). No payment-service call; no money moves. Chunk E will execute and mark paid.**

**What changed (`services/core-api`)**
- **`CalculatePayout` action** (`app/Actions/Payouts/`) + **`PayoutCalculation` value object** (`app/Support/Payouts/`)
  — pure computation, no DB; `net = gross − commission`, `payable = net + adjustments`, floored at 0 (never negative),
  gated on minimum threshold. All integer minor units (ADR-08).
- **`PayoutBuildService`** (`app/Services/Payouts/`) — full idempotency contract: fast-path
  `findByIdempotencyKey("payout:{vendorId}:{batchId}")` then row-lock (`SELECT … FOR UPDATE`) inside `DB::transaction`,
  catches `UniqueConstraintViolationException` as a replay. Calls `CalculatePayout` for the math; creates `Payout`
  (status=`pending`) + one `PayoutItem` per eligible order. Returns `null` for no-eligible-orders or below-threshold
  (rolls into next cycle). `buildAll(batchId)` enumerates vendor IDs and delegates.
- **Repositories expanded:**
  - `OrderRepositoryInterface` + `OrderRepository` — added `eligibleOrderIdsForVendorPayout(vendorId): array` (paid/
    partially_refunded orders, completed-event, not already in a paid payout for this vendor; `withTrashed` on
    ticket_types+events) and `eligibleVendorIdsForPayout(): array` (distinct vendor_ids for `buildAll`).
  - `LedgerEntryRepositoryInterface` + `LedgerEntryRepository` — added `vendorPayoutAmounts(vendorId, eligibleOrderIds):
    array` returning `{gross, commission, adjustments, per_order}`. Adjustments = completed-refund entries for eligible
    orders + ALL clawback entries for this vendor (post-payout recoveries from past cycles).
  - `PayoutRepositoryInterface` + `PayoutRepository` — added `findByIdempotencyKey`, `lockByIdempotencyKey`,
    `createPayout`, `createPayoutItem`, `list` (paginated, filterable by status/vendor_id).
- **Admin endpoints:**
  - `GET /api/v1/admin/payouts` — paginated list, filterable by `status` + `vendor_id`; `throttle:read`.
  - `POST /api/v1/admin/payouts/build` — trigger build run; optional `vendor_id` + `batch_id` (defaults to today's
    ISO date); idempotent; returns 201 with payout list; `throttle:write`.
- **`PayoutController`**, **`BuildPayoutsRequest`**, **`PayoutResource`** (enums as `{value,label}`, ISO-8601
  timestamps, integer amounts), **`lang/en/api.php` `payouts.*`** added.

**Decisions**
- **Idempotency: fast-path + row-lock + unique-constraint** — three-layer guard mirrors the charge/refund pattern
  (ADR-09). Fast-path avoids a transaction for replays; row lock collapses concurrent workers; unique constraint is
  the final backstop so a duplicate INSERT is caught and returned rather than crashing.
- **`eligibleOrderIdsForVendorPayout` does NOT exclude pending-payout orders** — the not-settled guard is `whereDoesntHave`
  against *paid* payouts only. Pending/approved/processing payouts are intentionally re-eligible so an aborted Chunk-E
  execution can retry via a new batch without data loss. The idempotency key prevents duplicate pending rows for the same
  batch window.
- **Clawback adjustments are global (not scoped to eligibleOrderIds)** — by definition clawbacks only exist for already-
  disbursed orders (past paid cycles), so including them globally correctly reduces the current cycle's payable without
  double-counting the new eligible orders.
- **`eligibleVendorIdsForPayout` is a fast pre-filter** — it uses a raw JOIN (bypasses soft-delete global scopes) to
  enumerate vendor IDs; `buildForVendor` performs the exact per-vendor eligibility check. False positives are fine here
  since `buildForVendor` returns null for empty results.

**financial-logic-reviewer findings + fixes (applied same session)**
- **C-1 (fixed):** `eligibleOrderIdsForVendorPayout` was guarding against `paid` payouts only — a second pending batch
  for the same vendor would double-include every order. Fixed: guard now excludes all non-`failed` statuses
  (`whereNotIn(status, [Failed])`), so pending/approved/processing payouts all block re-inclusion.
- **C-2 (fixed):** missing `UNIQUE(payout_id, order_id)` on `payout_items`. Added to migration as DB-level guard.
- **H-1 (fixed):** commission-reversal ledger entries (`entry_type=Commission, subject_type=refund`) were silently
  dropped from refund adjustments. Fixed: refund adjustment query now fetches both `Refund` and `Commission` entries
  with `subject_type=refund`. Vendors were being shorted their returned commission on partially-refunded orders.
- **H-2 (fixed):** `net` column was storing `payable` (post-adjustment amount), making `gross − commission ≠ net`.
  Added separate `payable` column to migration + model + service; `net` now correctly stores `gross − commission`.
  `PayoutResource` exposes both.
- **H-3 (fixed):** clawback entries were summed globally, risking double-application across cycles. Fixed: scoped to
  entries `created_at > last paid payout updated_at` for this vendor (new clawbacks only).
- **H-4 (fixed):** doc comment on `CalculatePayout` incorrectly stated `net` is always ≥ 0. Fixed.
- **M-1 (fixed):** `batch_id` was `nullable()` in migration but is always required. Changed to non-nullable.
- **M-4 (fixed):** `index` endpoint read query params directly without validation. Added `ListPayoutsRequest` FormRequest
  validating `status` (enum), `vendor_id` (max 26), `per_page` (1–100).
- **M-3 (new test):** `test_vendor_with_mixed_event_statuses_only_settles_completed_event_orders`.
- **H-1 regression test:** `test_commission_reversal_included_in_payout_adjustments` — proves the fix.
- Accepted without change: M-2 (soft-delete on ticket_types — raw JOIN is intentional; existing comment),
  M-5 (per-order settled_amount doesn't account for per-order refunds — documented limitation for follow-up),
  N-2/N-3 (superseded by H-2 fix), N-1 (superseded by C-1 fix test rewrite).
- Also fixed two existing `RefundWebhookTest` fixtures that created `Payout` rows without the new `payable` column.

**Verification**
- `composer format` (Pint) clean. `php artisan test` — **186 passed (666 assertions)**, all suites green.
- 25 new tests (9 unit + 16 feature) across `CalculatePayoutTest` + `PayoutBuildServiceTest`. All reviewer-required
  regression tests added and passing.

**Next (slice 3 / Day 4)**
- Run `financial-logic-reviewer`, address findings, commit Chunk D.
- Chunk E: payout EXECUTION — `ProcessPayoutBatch` job, `PaymentClient::executePayout`, payout webhook receiver, mark
  `paid`, write `payout` ledger entries, notify vendor.

## 2026-06-29 — Day 4 (slice 3 · Chunk C): refund EXECUTION client + webhook settlement (core-api)
**Maps to:** Day 4 — refunds (PLAN.md); core-api `CLAUDE.md` §F + §H; `docs/erd.md` (refunds, ledger_entries,
payouts); ADR-09/10/13/14, ADR-20 (clawback), ADR-23. **Chunk C of slice 3 — closes the refund loop: core-api drives
payment-service refund execution and settles the signed result. Builds on Chunk A (request/policy) + Chunk B
(payment-service execution).**

**What changed (`services/core-api`) — mirrors the charge client + webhook EXACTLY**
- **`PaymentServiceContract::refund` + `PaymentClient::refund` + `RefundResult`** — POSTs to payment-service
  `/api/v1/refunds` with `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}`, a deterministic `Idempotency-Key`
  (`refund:{id}`), and `LogHelper::traceHeaders()`; `->throw()` so non-2xx surfaces to the job.
- **`ExecuteRefundJob` (rewritten from the Chunk-A stub) + `RefundExecutionService`** — flips the refund
  `requested → pending` (row-locked) BEFORE the call, then POSTs; **never marks it completed locally**. 5xx/timeout →
  bubbles for a backed-off retry (refund stays pending, key reused → no double refund); **4xx → fast-fail**, resolve
  the refund `failed` locally (no webhook will come), no ledger. No charge-of-record (`external_ref` missing) → fail
  locally rather than hang pending.
- **`POST /api/v1/internal/payments/refund-webhook`** (`RefundWebhookController` + `RefundWebhookRequest`) gated by
  the **same** `webhook.signature` middleware as the charge webhook (bearer + raw-body HMAC, verified before parsing;
  401 on either failure). **`ProcessRefundWebhookService`** runs ONE transaction with the order row locked, replay-safe
  first (no open refund → 200 no-op). On `completed`: mark the refund completed; write the **signed reversal ledger
  per owning vendor** — `−refund` (sale reversal) **+** `+commission` (platform returns its cut; sale+reversal net to
  zero, ADR-23) — or a **`clawback`** in place of `−refund` when the vendor was **already paid out** for the order (a
  `paid` payout_item, ADR-20/13). Void `valid → refunded` tickets + mark the order `refunded` when cumulative refunds
  reach the total, else `partially_refunded`. Enqueue `SendRefundConfirmationJob` (publish-only) after commit. On
  `failed`: mark the refund failed, no ledger, no ticket/order change.
- **Repos:** `RefundRepository` (+`findForUpdate`, `lockOpenForOrder`, `markPending|markCompleted|markFailed`),
  `OrderRepository` (+`markRefunded|markPartiallyRefunded`), `TicketRepository` (+`voidValidForOrder`, status-guarded),
  new **`PayoutRepository`** (`orderSettledPaidForVendor` for the clawback decision). New
  `RefundWebhookMismatchException` (422). Lang `api.refunds.webhook_*` added.
- **Cross-service config:** core-api reuses `PAYMENT_SERVICE_URL`/`PAYMENT_SERVICE_TOKEN` (outbound refund) and
  `CORE_API_BEARER_TOKEN`/`CORE_API_WEBHOOK_SECRET` (inbound refund webhook — same keys as the charge webhook).
  Fixed payment-service `.env.example` `CORE_API_REFUND_WEBHOOK_URL` to the agreed `…/internal/payments/refund-webhook`
  path. Matching `[PLACEHOLDER]`s confirmed in both services.

**Decision promoted → `docs/technical-decision-log.md` ADR-30** (async refund mirroring the charge path; reversal+
commission ledger with clawback fallback; correlate by the order's single open refund; proportional per-vendor split;
full-refund ticket voiding). Documented limitation: Chunk A didn't persist per-item refund selection, so a **partial**
refund's per-vendor split is proportional and its specific tickets aren't voided — money totals stay correct; exact
per-item handling is the `refund_items` follow-up.

**Verification**
- `composer format` (Pint) clean. `php artisan test` — **157 passed (558 assertions)**, existing suites green.
- New: `ExecuteRefundJobTest` (6 — correct auth/key/body + flip-to-pending; 5xx retryable; timeout; 4xx→failed;
  re-dispatch reuses key, no double refund; terminal no-op) and `RefundWebhookTest` (9 — completed→reversal ledger +
  tickets voided + order refunded + confirmation; 401 bad/missing signature mutates nothing; replay no-op; failed→no
  ledger; amount mismatch 422; partial→partially_refunded + tickets valid; **clawback** when already paid out; unknown
  order no-op). `Http::fake`/`Queue::fake`, no real Redis.
- `financial-logic-reviewer` run on the new refund client + settlement path (findings triaged in the follow-up).

**Next (slice 3 / Day 4)**
- Out-of-policy `<24h` contest → `dispute` creation + admin mediation (ADR-11); event-cancellation **mass** 100%
  refund fan-out (ADR-23). Then **payouts**: `CalculatePayout`, threshold, `ProcessPayoutBatch`, payout execution +
  webhook (reusing this refund/charge client+webhook pattern).
- `refund_items` persistence so partial refunds void exact tickets + split per vendor exactly.

## 2026-06-29 — Day 4 (slice 3 · Chunk B): refund EXECUTION (payment-service)
**Maps to:** Day 4 — refunds (PLAN.md); payment-service `CLAUDE.md` §A.4/§B/§D/§E/§G; `docs/system-architecture.md`
§3.5; root comms/auth matrix; ADR-09 (idempotency), ADR-10 (shared-secret bearer + HMAC webhook), ADR-13 (append-only
ledger). **Chunk B of slice 3 — mirrors the Chunk-B(charge) path exactly: idempotent create → async gateway resolve →
signed webhook. Builds on core-api Chunk A (refund request/policy); core-api's refund-webhook receiver is a later chunk.**

**What changed (`services/payment-service`)**
- **`POST /api/v1/refunds`** behind `EnsureServiceToken` + named `throttle:refunds` — not publicly reachable, mirroring
  `/payments`. `CreateRefundRequest` validates `payment_ref` (must be a real charge), `amount` (integer, min:1),
  `currency` (size:3), `reason` (nullable); `Idempotency-Key` is folded from the header → 422 if missing. Returns the
  **pending** refund (201) via `RefundResource` ({value,label} enums, ISO-8601, never any card field).
- **`RefundService`** mirrors `ChargeService`: (1) `createRefund` reserves the pending `Refund` idempotently
  (key→`refund_id`; same key+body replays, different body → 409; concurrent unique-violation resolves as a replay),
  copying `gateway` + `order_id` from the original charge so the row is self-describing; (2) `resolve` rolls the
  **same gateway** that processed the charge OUTSIDE the transaction, then locks + re-checks terminal + persists +
  appends ONE `transactions` row (`type=refund`, **NEGATIVE** signed amount on success, 0 on failure, `gateway_ref`
  only). Idempotent under the row lock — a retry never re-refunds or double-writes the ledger. Local sanity guard:
  a single refund may not exceed the original charge (422) — cumulative validation stays core-api's job.
- **`DeliverRefundResultJob`** mirrors `DeliverChargeResultJob`: persists the result first, then POSTs the terminal
  result to core-api's refund webhook with `Authorization: Bearer ${CORE_API_BEARER_TOKEN}` **and**
  `X-Signature = hmac_sha256(RAW_BODY, CORE_API_WEBHOOK_SECRET)` over the exact bytes, plus the forwarded
  `Log-Trace-ID`; retryable with backoff, `ShouldBeUnique` per refund, graceful `failed()` logging. Payload carries
  `refund_ref`/`payment_ref`/`order_id`/status/amount only — never card data.
- **New:** `RefundStatus` enum (`pending|completed|failed` — matches core-api/the §3.5 contract), `refunds` migration
  (ULID/foreignUlid, `restrictOnDelete` — never delete financial records), `Refund` model + `RefundFactory`,
  `RefundRepository` (+interface, bound), `RefundExceedsChargeException`. Config: `services.core_api.refund_callback_url`
  (+`.env.example` `CORE_API_REFUND_WEBHOOK_URL`). Lang `api.refunds.*` added.

**Decision (noted):** the §3.5 doc sketched `/refunds` as a synchronous `200/completed`; I made it **async + pending +
signed webhook**, identical to the charge path, so both money flows share one shape, one idempotency story, and one
delivery/retry guarantee. (To promote into the decision log on review if accepted.)

**Verification**
- `composer format` (Pint) clean. `php artisan test` — **45 passed (242 assertions)**, existing charge suites green.
- New tests: `RefundEndpointTest` (401/403, missing-key 422, bad-amount 422, unknown payment_ref 422, exceeds-charge
  422, valid→pending+queued, idempotent same-key returns same refund), `RefundIdempotencyTest` (pending create copies
  gateway/order; same key+body→same; different body→409), `RefundResolutionTest` (forced success→completed + NEGATIVE
  ledger row keyed to the charge; forced failure→failed + 0 row; double-resolve writes one row), `RefundWebhookTest`
  (job resolves then posts a verifying HMAC + bearer + trace; tamper changes the HMAC). `Http::fake`/`Queue::fake`/
  forced outcome — hermetic.
- `financial-logic-reviewer` run on the new refund path — triaged:
  - **Fixed C-1:** refund now executes only against a **succeeded** charge — a pending/failed charge never captured
    money, so `ChargeNotRefundableException` (422) rejects it (+ endpoint tests for pending & failed charges).
  - **Fixed C-2:** the `transactions` ledger FK was `cascadeOnDelete` (contradicting append-only/ADR-13 and the
    `restrictOnDelete` I used on `refunds`) → changed to `restrictOnDelete` so ledger history can't silently vanish.
  - **Fixed H-1:** refund currency must match the original charge (`RefundCurrencyMismatchException`, 422) — prevents
    a mixed-currency ledger poisoning reconciliation (+ test).
  - **Fixed N-3:** dropped `reason` from the idempotency `requestHash` (mirrors the charge path) — a replay differing
    only in the descriptive `reason` now replays the original refund instead of spuriously 409-ing.
  - **Added H-4 test:** a delivery that 500s then 200s re-sends the webhook without re-refunding (one ledger row, two
    delivery attempts) — proves the persist-first guarantee.
  - **Documented, no change:** **H-2** (shared idempotency-key namespace) — core-api namespaces keys per operation
    (`charge:…` vs `refund:…`), so a cross-operation collision isn't reachable; a `type` column on the shared index
    would also touch the charge path, so it's noted as future hardening. **H-3 / N-2** — the redundant-gateway-roll is
    harmless for the simulator (the inner row-lock re-check discards the loser); a real gateway would need a
    gateway-level idempotency key (same as the charge path). `RefundResource` keeps parity with `PaymentResource`
    (no `updated_at`); the webhook already carries `occurred_at`.

**Next (slice 3)**
- **Chunk C:** core-api refund-webhook receiver — verify HMAC + bearer, idempotently flip the refund `requested→pending`
  →`completed|failed`, write the reversal `ledger_entry` (−refund; −commission/clawback for cancellation, ADR-23), set
  order `refunded|partially_refunded`, mark tickets `refunded`; then payout calc/batch.

## 2026-06-29 — Day 4 (slice 3 · Chunk A): refund REQUEST + POLICY (core-api)
**Maps to:** Day 4 — refunds/payouts (PLAN.md); core-api `CLAUDE.md` §F Refunds + §H; `docs/erd.md` (refunds,
ledger_entries); ADR-08/09/11/13/14/20/23. **Chunk A of slice 3 — request + policy only; execution (payment-service
call + reversal ledger) is Chunk B/C. No money moves and no ledger row is written in this chunk.**

**What changed (`services/core-api`)**
- **`RefundPolicy`** (`app/Support/Refunds/`, pure, no DB) + **`RefundDecision`** value object — decides eligibility
  and the auto-derived amount from `(reason, event starts_at, now, selected line base, original charge,
  already-refunded)`. Bands: **>48h → 100%**, **24–48h inclusive → 50%**, **<24h → 0%**; **event-cancellation →
  flat 100%** (ADR-23). Integer minor units only, **half-up** rounding via the basis-points trick (mirrors
  `CalculateCommission`); result **capped** so cumulative refunds never exceed the charge. Time math uses raw
  timestamps (no Carbon diff sign/float ambiguity).
- **`RefundService`** + **`RefundRepository`** (+interface, bound) — creates a `requested` refund row **idempotently**:
  **one open refund per order** (`requested|pending`), re-asserted under a `SELECT … FOR UPDATE` on the order row, so a
  duplicate request returns the existing refund (no second row, no second job) — mirrors checkout/charge idempotency
  (ADR-09/24). Amount + reason + policy band are snapshotted; the attendee never names an amount. New repo methods:
  `OrderRepository::findForRefund` (eager-loads items→ticketType→event `withTrashed` for historical events),
  `PaymentRepository::succeededForOrder`, `RefundRepository::findOpenForOrder` / `refundedTotalForOrder`.
- **`ExecuteRefundJob`** — dispatched **only** for a freshly-created, policy-approved refund (controller guards on
  `wasRecentlyCreated`, `afterCommit`); never for a duplicate/ineligible request. Guarded no-op unless the refund is
  still `requested`. **Deliberately does NOT call payment-service or write any ledger** — that lands in Chunk C.
- **Endpoints** (`/api/v1`, envelope, FormRequest, role-gated, `throttle:refund`, Controller→Service→Repository):
  attendee `POST orders/{order}/refund` (own paid order, ownership via new `OrderPolicy::refund`); admin
  `POST admin/orders/{order}/refund` (can set `reason=event_cancelled` → 100%, via `OrderPolicy::initiateRefund`).
  New `RefundController`, `RequestRefundRequest`, `InitiateRefundRequest`, `RefundResource`.
- **Enums/model/schema:** added `RefundReason` (`attendee_requested|event_cancelled`) and a `Requested` case to
  `RefundStatus` (+ `isOpen()`/`isTerminal()`); `Refund` casts `reason`→enum, adds `isOpen()`; refunds migration
  default `requested`; added `RefundFactory` (the model referenced a missing factory). Lang `api.refunds.*` added.
- **`<24h` is treated as out-of-policy** here — the request is rejected (422); the contest → dispute path (ADR-11)
  is a later slice, so no dispute rows are created in this chunk.

**Decision promoted → `docs/technical-decision-log.md` ADR-29** (refund request/policy split from execution; one
open refund per order under the order row lock; `requested` status before any money moves). ERD updated: refunds
`status` now `requested|pending|completed|failed`, `reason` is the policy category, + a relationship note.

**Verification**
- `composer format` (Pint) clean. `php artisan test` — **141 passed (493 assertions)**, existing suites green.
- New: `tests/Unit/RefundPolicyTest` (12 cases — bands incl. exact 24h/48h edges, cancellation override, past event,
  partial base, half-up rounding, fully-refunded + partial cap) and `tests/Feature/Refunds/RefundRequestTest`
  (11 cases — happy 100%/50%, idempotent re-request = same refund + one job, <24h rejected with no row/job, unpaid
  rejected, partial subset amount, partial over-quantity rejected, 401/403 ownership/role, admin cancellation 100%
  inside the 0% window). Both assert **`ledger_entries` count 0** and no payment/`quantity_sold`/order-status mutation.
- `financial-logic-reviewer` run on the new money path — triaged:
  - **Fixed C-1:** the cumulative-refund cap + policy decision now run **inside** the `DB::transaction` after
    `lockForUpdate` (were read outside the lock), so the cap can never be computed from stale data. (In Chunk A
    alone there was no actual over-refund — the order-scoped one-open-refund guard blocks a second row and nothing
    completes refunds yet — but this makes the invariant authoritative for Chunk C.)
  - **Fixed C-2:** the `order_item_id` `exists` rule is now scoped to the bound order (`->where('order_id', …)`), so
    a line id from another order fails validation; the service ownership re-check stays as defence in depth.
  - **Fixed N-2/N-3 + H-4 doc:** test helper is seconds-precise; `refunds.payment_id` FK is `restrictOnDelete`
    (never let a refund vanish with its charge — ADR-15); `ExecuteRefundJob` now documents the Chunk-C
    `requested→pending`-before-the-external-call contract.
  - **Added test (H-1):** a fully-refunded order (seeded completed refund = charge) is refused `already_refunded`
    with no new row/job — exercises the cap end-to-end.
  - **Rejected N-4:** the reviewer asked for `SoftDeletes` on `Refund`, but ADR-15 lists refunds as **never
    deleted** (lifecycle via status) — adding it would contradict the documented policy; left as-is.
  - **Deferred to Chunk C (documented):** H-2 — per-line already-refunded **quantity** tracking (e.g. an
    `order_items.refunded_quantity` or subquery) belongs with execution; today the cumulative money cap is the
    backstop and no completed partial refunds can exist yet.

**Next (slice 3)**
- **Chunk B/C:** refund execution — `PaymentServiceContract::refund` call with idempotency key, signed result →
  reversal `ledger_entry` (−refund, and −commission/clawback for cancellation per ADR-23), flip `requested`→`pending`
  →`completed|failed`, set order `refunded|partially_refunded`, mark tickets `refunded`. Then payout calc/batch.
- Out-of-policy `<24h` contest → `dispute` creation + admin mediation (ADR-11).

## 2026-06-29 — Day 3 (slice 2 · Chunk E): end-to-end purchase-loop proof — closes slice 2
**Maps to:** Day 3 — prove the whole money path holds together (PLAN.md); core-api `CLAUDE.md` §F.3–5 + §H + §I
(required order/inventory coverage); ADR-07/09/10/13/14/17. **Chunk E (final) of the 5-chunk slice; A–D done.
Slice 2 complete; next is slice 3 (refunds + payouts).**

**What changed (`services/core-api`) — wiring + proof, no new features**
- **`PurchaseLoopEndToEndTest`** (new, 4 cases) drives the REAL code at every hop and fakes only what crosses a
  process boundary (core-api and payment-service are separate apps/DBs): checkout (real) → `InitiateChargeJob`
  → `ChargeOrderService`/`PaymentClient` (charge POST `Http::fake`d) → signed webhook constructed exactly as
  payment-service's `DeliverChargeResultJob` signs it (HMAC over raw body) → real webhook receiver/settlement.
  Cases: (1) **success** → pending order+hold, charge posted with bearer+Idempotency-Key, payment ref recorded,
  signed webhook → order paid, 2 valid unique-QR tickets, `quantity_sold` moved, signed +sale/−commission ledger,
  confirmation enqueued once; (2) **forced failure** → payment failed, nothing issued/settled, then back-date
  holds + run `holds:release-expired` → hold released, order expired, inventory never consumed; (3) **expiry-cron
  vs settled order** → after settlement a late sweep must not flip `converted`→`released` or expire a paid order;
  (4) **idempotency e2e** → webhook replay + charge re-dispatch produce no double tickets/ledger/`quantity_sold`
  and exactly one payments row + one confirmation.
- **Fix (CRITICAL-2 from the loop review):** `TicketHoldRepository::releaseDueActiveHolds()` bulk UPDATE now
  re-asserts `status = active`, so the expiry cron can't clobber a hold a concurrent webhook just `converted`
  back to `released` (the snapshot→update window raced a settlement). Pure safety; happy path unchanged.

**financial-logic-reviewer — full loop (checkout → charge → webhook → issuance → ledger) — triaged**
- **Fixed:** **CRITICAL-2** (above) + a new e2e test proving the cron can't corrupt a settled order. **HIGH-1** —
  `issueTicketsAndSettle` now resolves the vendor FIRST and throws `OrderSettlementIntegrityException` (new) when a
  soft-deleted `ticket_type`/`event` makes `vendor_id` null; thrown inside the settlement transaction it rolls the
  whole thing back (no tickets, no `quantity_sold`, no ledger), bubbles as a loud 500, and leaves the order `pending`
  for reconciliation/expiry — money never moves without a vendor ledger row. New `PaymentWebhookTest` case proves the
  abort + zero mutations.
- **Documented / not changed (with rationale):**
  - **CRITICAL-1** (null `commission_rate` → settlement throws) — **overstated/unreachable**: every creation path
    sets it (`CheckoutService` writes the setting-or-`0.10` default; `OrderFactory` sets `'0.1000'`), and the Chunk-D
    `CalculateCommission` guard fails loud as a backstop. Latent looseness only: the column is `nullable`. *Follow-up
    (slice 3+):* tighten to `NOT NULL DEFAULT '0.1000'`.
  - **HIGH-2** (`ResolveTicketPrice` casts `group_discount` via `(float)` before scaling) — safe in practice:
    `round()` recovers the exact integer for every ≤4-dp rate in [0,1]; the rest of the math is integer. *Follow-up:*
    parse the decimal string to basis points like `CalculateCommission` for invariant purity.
  - **NIT-1** `OrderItem` missing `UPDATED_AT = null`; **NIT-2** `SendOrderConfirmationJob` dispatched after-commit
    (real fix would dispatch inside the txn with `->afterCommit()`; notification-only, lands with that slice);
    **NIT-3/4** cross-service `idempotency_key` placement + `'0.10'` vs `'0.1000'` cosmetics.
- **Note:** the reviewer flagged "no `CalculateCommission` unit test" — it **already exists** (`tests/Unit/
  CalculateCommissionTest.php`, added in Chunk D: half-up + throw-on-blank); the static review just didn't see it.

**Decisions (promoted → `docs/technical-decision-log.md`)**
- **ADR-27 — End-to-end tests fake only the true process boundary.** With two separate Laravel apps/DBs, the
  cross-service hops (outbound charge POST, inbound signed webhook) are faked/replicated at the wire while every
  core-api decision runs for real; payment-service's own charge/idempotency/signing logic is covered by its suite.
- **ADR-28 — Expiry sweep is write-guarded by status** so it is safe under a race with settlement (extends ADR-07's
  read-time-expiry stance to the write path).
- (HIGH-1 settlement-integrity guard recorded under the ADR-26 "refuse to mis-settle" family.)

**Verification** (gate run locally — Laragon, PHP 8.4, sqlite `:memory:`; the assistant environment has no PHP
runtime, so counts are confirmed on the local run)
- `composer format` (Pint) — clean. `php artisan test` (core-api) → **118 passed** (was 117; +1 for the new
  soft-delete-abort guard test): `PurchaseLoopEndToEndTest` (4: success, failure+expiry, expiry-vs-settled,
  idempotent replay+re-dispatch) + the HIGH-1 guard case in `PaymentWebhookTest`. payment-service suite unchanged →
  **29 passed (185 assertions)**. *(Assertion total to be confirmed on the local run.)*

**Next (slice 3)**
- Refunds (policy in core-api, execution in payment-service) + payouts (`CalculatePayout`, threshold, batch,
  no double-pay). Slice-2 decisions promoted (ADR-27/28; HIGH-1 guard under ADR-26). Remaining documented follow-ups
  to consider when touching those paths: CRITICAL-1 (tighten `commission_rate` to NOT NULL) and HIGH-2
  (`ResolveTicketPrice` decimal-string parse).

## 2026-06-28 — Day 3 (slice 2 · Chunk D): core-api payment webhook receiver — closes the purchase loop
**Maps to:** Day 3 — core-api applies the payment-service charge result and issues tickets (PLAN.md); core-api
`CLAUDE.md` §F.3–4 (order→payment→tickets) + §H (inter-service); root `CLAUDE.md` comms/auth matrix; ADR-07
(holds/locking), ADR-09 (idempotency), ADR-10 (signed callback), ADR-13/14 (ledger/commission snapshot), ADR-17
(orders 1:N payments). **Chunk D of the 5-chunk slice; A–C done, E (frontend wiring) upcoming.**

**What changed (`services/core-api`)**
- **`POST /api/v1/internal/payments/webhook`** — a service callback (NOT `auth:sanctum`), gated by the new
  **`VerifyPaymentWebhook` middleware** (`webhook.signature`): shared-secret bearer (`hash_equals`) **then** an
  HMAC-SHA256 of the **raw body** (`$request->getContent()`, pre-parse) vs `X-Signature` (`hash_equals`). Either
  failure → 401, nothing downstream runs. Matches payment-service `DeliverChargeResultJob`'s signing exactly.
- **`ProcessPaymentWebhookService`** — one `DB::transaction` with the order row `lockForUpdate`. Guard order:
  unknown/non-pending order → 200 no-op (replay-safe); amount+currency must equal the order's → else 422
  (`WebhookAmountMismatchException`), mutate nothing. On success: payment row → succeeded, holds → converted,
  one valid QR `Ticket` per held unit, `quantity_sold` incremented **here** (never at checkout), signed per-vendor
  `sale`(+)/`commission`(−) `ledger_entries` (split via order_item→ticket_type→event→vendor, integer math on the
  order's snapshotted `commission_rate`), then `SendOrderConfirmationJob` dispatched **after commit**. On failure:
  payment → failed, order left `pending` for the hold-expiry safety net.
- **Supporting:** `CalculateCommission` action (pure integer, half-up); `TicketRepository` + `LedgerEntryRepository`
  (+ contracts, bound in `RepositoryServiceProvider`); `markPaid`/`lockForUpdate` (Order), `markStatus`/
  `findByExternalRefForOrder` (Payment), `convertActiveForOrder` (TicketHold), `incrementSold` (TicketType);
  `SendOrderConfirmationJob` (publish point — Redis wiring lands with the notification slice).
- **Cross-service wiring (Step 0):** core-api `services.webhook.{bearer_token,secret}` ← `CORE_API_BEARER_TOKEN`
  / `CORE_API_WEBHOOK_SECRET`; **both** services' `.env.example` carry the SAME `[PLACEHOLDER]` keys so one secret
  is wired per pairing. No real secrets anywhere.

**financial-logic-reviewer — findings triaged**
- **Fixed:** **C-1/C-2 (oversell)** `convertActiveForOrder` now filters `expires_at > now()`, and the service
  aborts settlement (no tickets / no `quantity_sold` / order stays pending) when **zero** holds convert — a charge
  that confirms after the 15-min window can't issue against seats already freed for other buyers. **C-3 (audit
  integrity)** a success with no matching payment row is now a logged no-op (never mark an order paid with no
  payment of record). **H-3 (money math)** `CalculateCommission` throws on a blank/non-numeric rate instead of
  silently charging 0 commission.
- **Documented / not changed:** **H-1** `payment_ref`↔`external_ref` coupling holds in the simulator (Chunk C
  contract); **H-2** throwing the 422 inside the transaction is correct (Laravel rolls back); **H-4** mitigated by
  the existing HMAC + order-scoped payment lookup + amount match; **N-1** tickets have no `created_at` (by design;
  revisit for dispute audit); **N-2** no rate-limit on the internal route (bearer+HMAC is the guard; a named
  limiter is optional hardening); **N-4** `SendOrderConfirmationJob` is a stub until the notification slice.

**Decisions (promoted → `docs/technical-decision-log.md`)**
- **ADR-25 — Settlement honors only NON-EXPIRED holds.** A successful charge arriving after the hold lapsed does
  **not** issue tickets (would oversell — seats were freed at read time); the order is left `pending` for the expiry
  net and the recorded success becomes a refund concern in a later slice. Extends ADR-07's read-time-expiry rule into
  the webhook path. **Assumption documented:** the `convertActiveForOrder(...) === 0` all-or-nothing guard is correct
  only because all holds for an order share one `expires_at` (created together at checkout); heterogeneous hold
  lifetimes would require a per-line converted-vs-issued check.
- **ADR-26 — No order is marked paid without its payment of record** — a success webhook whose `payment_ref` matches
  no core-api `payments` row is a logged no-op (defence-in-depth beyond the HMAC); pairs with `CalculateCommission`
  refusing a blank/non-numeric rate rather than mis-settling.

**Verification** (gate run locally — Laragon, PHP 8.4, sqlite `:memory:`; the assistant environment has no PHP
runtime, so the commit is gated on this confirmed-green local run)
- `composer format` (Pint) — clean. `php artisan test` (core-api) → **113 passed (359 assertions)**; new
  `PaymentWebhookTest` (7 incl. bad-sig 401, replay no-op, failure, amount mismatch, **post-expiry no-oversell**,
  unknown-payment no-op, multi-vendor split) + `CalculateCommissionTest` (4, incl. half-up + throw-on-blank).
  payment-service suite unchanged → **29 passed (185 assertions)**.

**Next**
- **Chunk E:** end-to-end purchase-loop test (checkout→charge→webhook→issuance→ledger, forced success + forced
  failure) and a financial-logic-reviewer pass over the full loop; confirm idempotency end-to-end. Closes slice 2;
  slice 3 is refunds + payouts.

## 2026-06-28 — Day 3 (slice 2 · Chunk C): core-api → payment-service charge client + queued InitiateCharge job
**Maps to:** Day 3 — core-api initiates the charge for a pending order (PLAN.md); core-api `CLAUDE.md` §H
(inter-service clients) + §F.3 (order→payment); root `CLAUDE.md` comms/auth matrix; ADR-09 (idempotency),
ADR-17 (orders 1:N payments / retry cardinality). **Chunk C of the 5-chunk slice; A+B done, D+E upcoming.**

**What changed (`services/core-api`)**
- **`PaymentServiceContract` + `PaymentClient` (HTTP impl).** `createCharge()` POSTs to payment-service
  `/api/v1/payments` with `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}`, a deterministic per-attempt
  `Idempotency-Key`, and `LogHelper::traceHeaders()`; base URL + token from `config/services.php` (env-driven).
  `connectTimeout(5)/timeout(10)` + `->throw()` so a non-2xx/timeout surfaces as an exception. Bound
  contract→impl in `RepositoryServiceProvider` (fakeable). `ChargeResult` VO carries only the gateway ref +
  pending status — **no card data**. Only `createCharge` for now; refund/payout join their slices.
- **`ChargeOrderService` (orchestration) + `InitiateChargeJob` (queued).** Job dispatched from
  `OrderController` (`->afterCommit()`, guarded by `status === Pending`) once checkout commits. The service:
  no-ops unless the order is still `pending`; `firstOrCreate`s the core-api `payments` row on the unique
  per-attempt `idempotency_key` (`charge:{orderId}:attempt:{n}`); calls the client; persists `external_ref`.
  Retryable (`tries=5`, backoff `[10,30,60,120]`), `ShouldBeUnique` per (order, attempt). **On 5xx/timeout the
  order STAYS pending** (job retries; hold-expiry is the safety net); **on 4xx the job fast-fails** (no retry —
  a permanent client error can't succeed by retrying) and the order still stays pending. Never marks paid.
- **`PaymentRepository` (core-api, new) + `find()` on `OrderRepository`.** `firstOrCreateForAttempt` (catches the
  concurrent unique-violation race → replay, mirroring `CheckoutService`); `recordExternalRef` does a **targeted
  `update()`** of only `external_ref` (never a stale full `save()` that could clobber a webhook-written status).
- **config/.env/factories:** `services.payment.{base_url,service_token,default_gateway}`; `.env.example` gains
  `PAYMENT_SERVICE_URL` + `PAYMENT_SERVICE_TOKEN=[PLACEHOLDER]` + `PAYMENT_DEFAULT_GATEWAY`. Added the missing
  `OrderFactory` + `PaymentFactory` (referenced by the models). `CheckoutTest`/`ReleaseExpiredHoldsTest` now
  `Queue::fake()` (checkout dispatches the charge job) + assert the job is pushed for a pending order.

**financial-logic-reviewer — findings triaged**
- **Fixed:** **H-2** `recordExternalRef` now a targeted `update()` (won't clobber a webhook-written status back to
  pending); **C-1/M-2** job fast-fails on 4xx (incl. an empty-token 401) instead of burning 5 retries, only 5xx/
  timeout retry; **H-1** `firstOrCreateForAttempt` catches the concurrent unique-violation → replay (consistent with
  `CheckoutService`); **H-4** dispatch uses `->afterCommit()`; **M-3** strengthened the re-dispatch test to assert
  `external_ref` isn't overwritten + added a 4xx-not-retried test.
- **Deferred/rejected (documented):** **H-3** "no ledger entry on charge initiation" — by design: core-api writes
  `ledger_entries` on **webhook success** (§F.4 / ADR-13), not on a pending attempt; the `payments` row records the
  attempt (Chunk D writes the settled ledger row). **C-2** `ShouldBeUnique` uses the cache store — ops note: prefer
  `CACHE_STORE=redis` in prod; **correctness does not depend on it** (firstOrCreate + unique key + gateway dedupe are
  the real guards). **M-1** `attempt` is always 1 today and retries reuse it (correct dedupe); the param is the
  documented seam for a *deliberate* new attempt (ADR-17 fresh key → new row). **N-1** `Payment` SoftDeletes — a
  pre-existing schema decision, out of Chunk C scope. **N-2** `LogHelper` is a canonical stub (not forked; token is
  never logged).

**Decisions (candidate for `docs/technical-decision-log.md`)**
- **Charge initiation is a queued job dispatched after checkout commit**; idempotency key is **deterministic per
  (order, attempt)** so a queue retry reuses the same `payments` row and de-dupes at the gateway (extends ADR-09/17
  to the core-api→payment call). **Failure policy:** 5xx/timeout → retry; 4xx → fast-fail; **order never leaves
  `pending` except via the webhook** (Chunk D) or hold-expiry — money never advances on a failed charge.
- **Gateway selection deferred** — Chunk C charges the configured `default_gateway`; per-order gateway choice at
  checkout is a later concern.

**Verification**
- `composer format` (Pint) — clean (`{"result":"passed"}`). `php artisan test` → **100 passed (282 assertions)** on
  `sqlite_testing :memory:` (`RefreshDatabase`); was 99 (pre-existing) → all green incl. the 6 new charge tests.
- New `Feature\Payments\InitiateChargeTest` (6): correct auth header + Idempotency-Key + body + a pending payments
  row; 5xx leaves order pending + retryable; timeout (`ConnectionException`) bubbles; re-dispatch reuses the key →
  one payment row, `external_ref` not clobbered; 4xx not retried; no-op when order already settled. `CheckoutTest`/
  `ReleaseExpiredHoldsTest` updated for the new dispatch (Queue::fake).
- *Not run:* real cross-service HTTP (payment-service faked via `Http::fake`); end-to-end loop is Chunk E.

**Next — slice 2 remaining chunks (await per-chunk approval):**
- **D:** core-api webhook receiver — verify HMAC + bearer, atomically flip order → paid, convert holds → issued QR
  tickets, increment `quantity_sold`, write the `ledger_entry`, enqueue order-confirmation; idempotent on replay.
- **E:** end-to-end purchase-loop test + financial-logic-reviewer pass.

## 2026-06-28 — Day 3 (slice 2 · Chunk B): payment-service charge endpoint + transactions ledger + signed webhook
**Maps to:** Day 3 — payment-service `POST /payments` + webhook callback (PLAN.md); payment-service `CLAUDE.md`
§C/§E/§G/§F/§H; ADR-09 (idempotency), ADR-10 (shared-secret bearer + HMAC webhook), ADR-13 (append-only ledger).
**Chunk B of the 5-chunk slice; A done, C–E upcoming.**

**What changed (`services/payment-service`)**
- **`POST /api/v1/payments`** behind the `service.token` shared-secret middleware + a new `throttle:payments`
  named limiter — **no public access**. Thin `PaymentController@store` → `ChargeService` → `PaymentResource`
  (201 `pending`). `CreatePaymentRequest` validates `order_id` (string), `gateway` (`Rule::in` from
  `Gateway::cases()`), `amount` (integer `min:1` — minor units, never float/0), `currency` (size:3), and folds
  the **`Idempotency-Key` header** into validated data → missing key is a clean **422** (mirrors core-api's
  `CheckoutRequest`). `PaymentResource` emits `{value,label}` enums + ISO-8601 + the fake `gateway_ref` — **no
  card field exists to leak**.
- **`transactions` append-only ledger (C-2 resolved).** Migration `…200003` (ULID pk, `foreignUlid payment_id`,
  `type`, **signed** `bigInteger amount`, currency, fake `gateway_ref`, **`created_at` only — no `updated_at`**).
  `Transaction` model (`UPDATED_AT = null`), `TransactionType` enum (charge|refund|payout), repo behind an
  interface. One row is written when a charge **resolves** (not on pending creation), mirroring core-api writing
  its `ledger_entry` only on a terminal result.
- **`ChargeService` gained `resolve()` + `scheduleResolution()`.** `resolve()` rolls the gateway **outside** the
  DB transaction, then **locks + re-checks terminal status + persists + appends the ledger row inside** the
  transaction — idempotent under job retry / duplicate dispatch (row lock is the authoritative single-write guard,
  ADR-07 philosophy). `PaymentRepository` gained `findForUpdate` (SELECT … FOR UPDATE) + `markResolved`.
- **`DeliverChargeResultJob` (queued, `ShouldBeUnique` by payment, retryable w/ backoff).** Resolves the charge
  (result persisted **first**, so a delivery failure never loses it), then POSTs the signed result to core-api:
  `X-Signature = hmac_sha256(raw_body, CORE_API_WEBHOOK_SECRET)` + a **separate** bearer (`CORE_API_BEARER_TOKEN`)
  + forwarded `Log-Trace-ID`. `occurred_at` is the **resolution** time (`payment.updated_at`), not delivery time,
  so a retried webhook keeps the true event timestamp. `failed()` logs gracefully (core-api receiver is Chunk D).
  Callback URL is **config-only, never from the request body (SSRF guard)**.
- **config/.env/lang:** `services.core_api.{callback_url,bearer_token,webhook_secret}`; `.env.example` gains the
  three `CORE_API_*` vars (all `[PLACEHOLDER]`); `api.php` lang gains `payments.created`,
  `payments.idempotency_key_required`, `errors.too_many_requests`.

**financial-logic-reviewer — findings triaged**
- **Fixed:** **H-3** gateway roll moved OUT of the DB transaction (lock held only for the write, not a slow
  gateway response); **audit-timestamp** `occurred_at` now = resolution time, not delivery time (correct on
  retry); **H-1** split the webhook **bearer token from the HMAC key** (a leaked `Authorization` header can no
  longer forge a signature); **H-2** added `ShouldBeUnique` (collapses replay-while-pending + at-least-once
  redelivery). Added the two flagged missing tests (replay-after-resolution returns the live terminal payment; no
  second job when already terminal).
- **Deferred/rejected (documented):** **`(payment_id,type)` unique on the ledger** — rejected: the row-lock +
  terminal-guard is the authoritative single-write guard (ADR-07/09 philosophy), and a `(payment_id,type)` unique
  would **break future partial refunds** (N refund rows per payment); a `gateway_ref` unique wouldn't help since a
  buggy double-resolve mints a *new* ref each roll. **`order_id` ULID rule** — kept as `string` per the chunk spec;
  core-api is the only caller and it's shared-secret-gated. **M-1** (failed-charge `amount=0` vs future refunds) +
  **M-2** (real-gateway `gateway_ref` uniqueness) — refund-chunk / real-gateway concerns, noted for later. **N-2**
  `LogHelper` is a canonical stub — not forked. **N-3** non-issue: 4 backoff gaps is correct for 5 tries.

**Decisions (candidate for `docs/technical-decision-log.md`)**
- **Webhook uses two distinct secrets** — `CORE_API_BEARER_TOKEN` (auth) + `CORE_API_WEBHOOK_SECRET` (HMAC key) —
  so bearer interception ≠ signature-forgery. Worth a line under **ADR-10**.
- **payment-service `transactions` ledger is append-only and written at charge *resolution*** (not creation);
  **failed charge = signed `amount` 0** so `SUM(amount)` is the honest net position. Worth a line under **ADR-13**.
- **Charge resolution = gateway-roll-outside-txn + lock-recheck-write-inside**; the DB row lock + terminal-status
  re-check is the authoritative idempotency guard for the ledger (extends **ADR-07/09** to the payment side).
- **Webhook callback URL is config-only (SSRF guard)** — never accepted from the request body.

**Verification**
- `composer format` (Pint) — clean (`{"result":"passed"}`). `php artisan test` → **29 passed (185 assertions)** on
  `sqlite_testing :memory:` (`RefreshDatabase`); was 15 → +14 new Chunk-B assertions-bearing tests.
- New coverage: `PaymentEndpointTest` (7) — 401 (no token) / 403 (bad token) / 422 (missing key, amount 0,
  unknown gateway) / 201 pending + job queued / idempotent same-key returns same payment;
  `ChargeResolutionTest` (5) — forced success (+amount ledger row) / forced failure (0 ledger row) / re-resolve is
  a no-op / replay-after-resolution returns live terminal payment / no job when already terminal;
  `ChargeWebhookTest` (2) — job resolves then posts a correctly-signed webhook (HMAC verifies with the secret over
  the exact bytes; bearer is the separate token; trace forwarded) / tamper changes the HMAC.
- *Not run:* docker MySQL apply (validated via sqlite `RefreshDatabase`; migration ordered after `payments`).
  No end-to-end cross-service run yet — core-api webhook receiver is Chunk D.

**Next — slice 2 remaining chunks (await per-chunk approval):**
- **C:** core-api → payment client in a queued job (shared secret + `Idempotency-Key`), triggered for a pending order.
- **D:** core-api webhook receiver — verify HMAC + bearer, atomically flip order → paid, convert holds → issued QR
  tickets, increment `quantity_sold`, write `ledger_entry`, enqueue order-confirmation; idempotent on replay.
- **E:** end-to-end purchase-loop test + financial-logic-reviewer pass.

## 2026-06-28 — Day 3 (slice 2 · Chunk A): payment-service money foundation — gateways + idempotent charge
**Maps to:** Day 3 — payment-service (StripeSim/PayPalSim + idempotency) (PLAN.md); payment-service `CLAUDE.md`
§B/§D/§F; ADR-07 (hybrid lock — referenced), ADR-09 (DB-backed idempotency). **Chunk A of a 5-chunk slice; B–E
are upcoming (listed under Next).**

**What changed (`services/payment-service`)**
- **Migrations:** `payments` (ULID pk, `order_id` ULID reference — orders live in core-api, so **no cross-service
  FK**; `gateway`, `status` pending|succeeded|failed, `amount` integer minor units, `currency`, `gateway_ref`
  nullable — a **clearly-fake** simulated ref only, **never** PAN/CVV/token) and `idempotency_keys` (mirrors
  core-api: unique `key` + `request_hash` + json `response_payload` + `status`).
- **Gateway abstraction (CLAUDE.md §B):** `PaymentGatewayContract` (charge/refund/payout + name/delay), an
  `AbstractGatewaySimulator` base, and `StripeSimulator` / `PayPalSimulator`, resolved by name via
  `GatewayManager`. Outcome is decided from a configurable **success_rate** and is **deterministic** two ways for
  tests — `force=succeed|fail` and a per-instance seeded RNG. `GatewayResult` is an immutable VO (succeeded +
  fake reference + gateway).
- **config/gateways.php:** `stripe_sim` + `paypal_sim`, each with `success_rate`, `delay_seconds`, and test-only
  `force`/`seed`, all env-driven. `.env.example` updated (gateway vars + `PAYMENT_SERVICE_TOKEN=[PLACEHOLDER]`)
  and re-aligned to the service's real docker config (mysql/redis/`eventhub_payments`).
- **Layering (Controller→Service→Repository→Model):** `ChargeService::createCharge()` (idempotent charge
  creation — persists a `pending` Payment + the key→`payment_id` mapping in one `DB::transaction`; same key+body →
  same Payment, same key+different body → 409 `IdempotencyKeyConflictException`, concurrent-duplicate → unique
  violation recovered as a replay). `Payment`/`IdempotencyKey` repositories behind interfaces, bound in a new
  `RepositoryServiceProvider`. `Payment`/`IdempotencyKey` models (ULIDs, enum/integer casts). `PaymentStatus` +
  `Gateway` enums. Payment/IdempotencyKey factories. `lang/en/api.php` payments group.
- **`EnsureServiceToken` middleware** (shared-secret bearer; missing → 401, wrong → 403, constant-time
  `hash_equals`, generic messages) registered as the `service.token` alias in `bootstrap/app.php`. No money route
  is wired yet (Chunk B), so nothing is publicly reachable.
- **composer.json:** added `format` (Pint) + `test` scripts (the scaffold lacked them). *(This commit also brings
  the previously-uncommitted payment-service scaffold into git.)*

**financial-logic-reviewer — findings triaged**
- **Fixed:** **H-2** gateway RNG moved off global `mt_srand`/`mt_rand` to a per-instance `\Random\Randomizer`
  (no cross-request RNG bleed); **H-3** generic 401/403 auth messages (no mechanism disclosure); **H-4** removed a
  dead computed `failure_rate` float config key; **M-3** `GatewayManager` now guards `is_a(...Contract)` before
  instantiating a driver; **M-4** added a deterministic concurrent-conflict test; **N-3/N-5** factory + test tidy.
- **Rejected/deferred (documented):** **C-1** "double-charge window" — not valid: both writes are in one
  `DB::transaction`, so the loser's unique-key violation rolls back its Payment too; mirrors the slice-1-reviewed
  `CheckoutService`. The new M-4 test **proves** it (loser replays the winner; `Payment::count()` stays 1).
  **C-2** `transactions` ledger — **deferred to Chunk B by design**: the ledger records *resolved* financial
  events (succeeded/failed), which happen at charge resolution (B), exactly as core-api writes its `ledger_entry`
  on webhook success, not on pending creation. **H-1/M-1/M-2** kept consistent with core-api. **M-5** (amount>0
  validation) → Chunk B FormRequest (validation belongs in the request layer). **N-1** moot — `Str::random()` uses
  `random_bytes()`, not `mt_rand`.

**Decisions (candidate for `docs/technical-decision-log.md`)**
- **payment-service idempotency mirrors core-api ADR-09** (DB `idempotency_keys` table → `response_payload`,
  *not* a column on `payments`); same key+body → same record, +different body → 409, concurrent-duplicate →
  unique-violation-recovered replay. Worth a one-line note under ADR-09 that both ends of the money path share the
  mechanics. **Payment-service `transactions` ledger is written at charge *resolution*, not creation** — promote
  alongside the Chunk B webhook work.

**Verification**
- `composer format` (Pint) — clean (`{"result":"passed"}`). `php artisan test` → **15 passed (137 assertions)**
  on `sqlite_testing :memory:` (`RefreshDatabase`).
- New coverage: `Unit\Gateways\GatewaySimulatorTest` (9) — forced succeed/fail, success_rate 1.0/0.0 edges, seeded
  reproducibility, refund/payout honour force, manager resolves each gateway + carries delay + rejects unknown.
  `Feature\Payments\ChargeIdempotencyTest` (4) — pending charge created, same key+body returns same record (no
  second charge), same key+different body → 409, **concurrent-duplicate replays the winner with no double charge**.
- *Not run:* docker MySQL apply (migrations validated via sqlite RefreshDatabase; `ulid`/`json`/named-index are
  MySQL-compatible). No HTTP test for `EnsureServiceToken` yet — added with the route in Chunk B.

**Next — slice 2 remaining chunks (await per-chunk approval):**
- **B:** `POST /api/v1/payments` (create charge → pending, idempotent, behind `service.token`) + the signed HMAC
  webhook callback to core-api; **add the append-only `transactions` ledger** (C-2) written on charge resolution;
  amount/currency validation in the FormRequest (M-5); auth feature tests (401/403).
- **C:** core-api → payment client in a queued job (shared secret + `Idempotency-Key`), triggered for a pending order.
- **D:** core-api webhook receiver — verify HMAC, atomically flip order → paid, convert holds → issued QR tickets,
  increment `quantity_sold`, write `ledger_entry`, enqueue order-confirmation; idempotent on replay.
- **E:** end-to-end purchase-loop test + financial-logic-reviewer pass.

## 2026-06-28 — Day 3 (slice 1): Checkout — order + 15-min holds, distributed lock, idempotency (core-api)
**Maps to:** Day 3 — checkout/holds/locking (PLAN.md, highest-value slice); CLAUDE.md §F Order processing;
ADR-07 (hybrid lock), ADR-09 (idempotency), ADR-24 (new — checkout mechanics).

**What changed**
- **`POST /api/v1/orders`** (auth:sanctum + role:attendee + throttle:checkout). Idempotency-Key is a required
  header (missing → 422). Produces a `pending` order + `order_items` + 15-min `ticket_holds` only — **no tickets
  issued, `quantity_sold` untouched** (those move on payment success, a later slice).
- **`CheckoutService`** — the core. Hybrid lock (ADR-07): a short-lived per-`ticket_type` `Cache::lock`
  (Redis in prod, array store in tests) acquired in **sorted id order** (deadlock-safe) fronts an authoritative
  `SELECT … FOR UPDATE` row lock taken inside the `DB::transaction`. Availability =
  `quantity_total − quantity_sold − SUM(active holds WHERE status=active AND expires_at > now())`, computed at
  **read time** under the lock. The whole multi-line cart is one transaction — a mid-cart failure persists nothing.
  Duplicate cart lines are merged per ticket type before the check. Idempotency (ADR-09): same key+body → same
  order (no new side effects), same key+different body → 409, concurrent-duplicate caught via the unique key and
  resolved as a replay.
- **`ResolveTicketPrice`** action — group-bundle pricing via **integer (basis-point) arithmetic** (no float drift):
  if `group_size` set and line qty ≥ group_size, every unit = `round(price × (1 − discount))` half-up.
- **`ReleaseExpiredHolds`** — `ReleaseExpiredHoldsService` + artisan command + every-5-min schedule
  (`withoutOverlapping`). Flips active+due holds → `released` and their still-`pending` orders → `expired`;
  idempotent, never touches converted holds / non-pending orders. Comment + design note: **correctness does not
  depend on this cron** — availability already ignores expired holds at read time.
- **Repositories** (contracts + Eloquent, bound in provider): `OrderRepository`, `TicketHoldRepository`
  (`sumActiveQuantityForTicketType`, `releaseDueActiveHolds`), `IdempotencyKeyRepository`, `SettingRepository`;
  `TicketTypeRepository` gained `lockForUpdate` + `findManyForCheckout`. 6 Orders domain exceptions, `CheckoutRequest`,
  `OrderResource` (items + holds + soonest `hold_expires_at` for the countdown), `OrderController`, `SettingFactory`,
  orders lang group.

**financial-logic-reviewer — findings addressed**
- **C-1 (fixed):** commission rate was a PHP float into a `decimal(5,4)` column. Now snapshotted as an exact
  decimal **string**; `OrderResource` returns the decimal string (e.g. `"0.1000"`), no float anywhere on the path.
- **C-2 (fixed):** `exists:ticket_types,id` accepted soft-deleted rows. Now `Rule::exists(...)->whereNull('deleted_at')`
  → a deleted ticket type is a clean 422.
- **H-1 (fixed):** purchasability + sales window are now **re-validated on the fresh locked row** inside the
  transaction (not just the pre-lock snapshot), so an event cancelled mid-checkout cannot create holds.
- **H-3 (fixed):** a missing attendee profile now returns 422 (guarded), not a 500.
- **H-4 (fixed):** pricing switched from float to integer basis-point math.
- **Accepted (documented, not changed):** **C-3** cron SELECT-then-UPDATE — guarded by `withoutOverlapping` + an
  idempotent `Released→Released` update + `status=pending` filter; revisit before wiring waitlist notifications to
  the expiry path. **H-2** concurrent-key recovery path is correct (unique-violation → replay). **N-2** order-total
  overflow is far outside realistic value bounds (max:50 lines × max:100 qty).

**Decisions promoted →** `docs/technical-decision-log.md` **ADR-24** (Idempotency-Key via required header;
group-bundle pricing rule; cart-line normalization; lock-contention → retryable 409).

**Verification**
- `composer format` (Pint) — clean. `php artisan test` → **94 passed (265 assertions)**; the prior 68 stay green.
- New coverage: `Orders\CheckoutTest` (20) — happy path (pending order, total/commission snapshot, no tickets,
  `quantity_sold` unchanged), group-bundle on/off, idempotent replay + different-body 409, **expired hold frees
  inventory**, **sequential oversell (exactly N succeed)**, **cache-lock-held blocks checkout (409)**, mixed-currency
  422, unpublished/cancelled event 422, closed sales window 422, soft-deleted ticket type 422, missing-key 422,
  missing-profile 422, 401/403. `Orders\ReleaseExpiredHoldsTest` (4). `Unit\ResolveTicketPriceTest` (4).
- **Concurrency caveat (documented):** the suite runs on **SQLite**, which serializes writes — the oversell test
  proves the inventory math + lock ordering, but the `SELECT … FOR UPDATE` row-lock guarantee under true parallel
  contention is the MySQL behaviour (verified by design; ADR-07). The cache-lock front is proven in isolation by the
  lock-held test. A real parallel load test against MySQL is listed as a "with more time" item.

**Next**
- Day 3 slice 2: payment-service (StripeSim/PayPalSim + idempotency + signed webhooks), the core-api → payment
  client in a queued job, and the webhook that flips the order to `paid`, converts holds → issued QR tickets,
  increments `quantity_sold`, and writes the `ledger_entry`.

## 2026-06-27 — Day 2: Vendor KYC review flow + capacity invariant closed (core-api)
**Maps to:** Day 2 — vendor onboarding & KYC (PLAN.md); CLAUDE.md §F Vendor onboarding & KYC, §J data protection.
Also closes the flagged event-capacity gap from the CRUD session.

**What changed**
- **STEP 0 — capacity invariant closed.** `EventService::update` now, when `capacity` is supplied, locks the event
  row (`lockForUpdate`) and rejects a capacity below `SUM(ticket_types.quantity_total)` →
  `CapacityBelowAllocatedException` (**422**). `EventService` now also depends on `TicketTypeRepositoryInterface`
  for the in-txn sum. Lowering capacity to *exactly* the allocated sum is allowed.
- **KYC state machine** on `KycStatus`: added `isTerminal()` and `canTransitionTo()` (pending → verified|rejected;
  verified/rejected terminal). New `InvalidKycTransitionException` (**409**).
- **Vendor repository** extended: `paginatePending` (uses `idx_vendors_kyc_status`, `submitted_at` not null),
  `lockForUpdate`, `update`, `addDocument`.
- **`VendorService`** (Controller→Service→Repository): `submitForReview` (txn under vendor lock; stamps
  `submitted_at`, keeps `kyc_status=pending`, clears any prior `rejection_reason`, attaches `kyc_documents`;
  re-submitting a *verified* profile → 409), `verify`/`reject` (txn under lock; guard `canTransitionTo`; stamp
  `reviewed_by`/`reviewed_at`; reject records `rejection_reason`). All decisions are lock-guarded so two concurrent
  admin reviews can't both flip a terminal status.
- **`VendorPolicy`** (auto-discovered): `submitKyc` = vendor owns its own profile; `reviewAny`/`review` = admin only
  (defence in depth behind the `role:admin` route middleware).
- **HTTP:** `VendorController` (`submitKyc`, `pending`, `verify`, `reject`); `SubmitKycRequest`
  (documents[].type ∈ trade_license|nid|bank_statement, storage_path is an opaque reference string),
  `RejectVendorRequest` (reason required); `KycDocumentResource` (omits `storage_path`); `VendorResource` expanded
  with review fields (`submitted_at`/`reviewed_at`/`rejection_reason`, `kyc_documents` via `whenLoaded`) — still
  **never** exposes `tin_bin`/`representative_nid`/`payout_account`/`webhook_secret`.
- **Routes:** `POST /vendor/kyc` (auth + role:vendor + throttle:write); `GET /admin/vendors`,
  `POST /admin/vendors/{vendor}/verify`, `POST /admin/vendors/{vendor}/reject` (auth + role:admin). Lang keys added
  under `events.capacity_below_allocated` + a new `vendors.*` group.

**Decisions (this session)**
- **Illegal KYC transition → 409** (state conflict), consistent with the event-lifecycle 409. **Capacity-below-
  allocated → 422.**
- **Submission allowed from pending or rejected (re-submit), blocked when verified.** A rejection is recoverable —
  the vendor fixes documents and re-submits, which resets to pending and clears the old reason.
- **PII/data-protection:** `storage_path` is a reference only (never raw bytes), encrypted at rest, and omitted from
  every resource; document bytes would be served via short-lived signed URLs (not built yet). `contact_phone`/
  `address` are returned to admins for review utility (not in the encrypted-secret set).

**Verification**
- `composer format` (Pint) — clean (`{"result":"passed"}`).
- `php artisan test` → **68 passed (192 assertions)**; all 53 prior tests stay green. New: 2 capacity tests in
  `EventTest` (reject-below-allocated, allow-to-exactly-allocated) + `Vendors\VendorKycTest` (13): submit happy/202,
  submit validation 422 + 401, verified-can't-resubmit 409, vendor & attendee blocked from review (403), admin
  list/verify/reject happy paths, reject-requires-reason 422, re-deciding terminal status 409, and two
  data-protection tests asserting the encrypted PII fields + `storage_path` never appear in any response body.

**Next**
- Day 3: checkout (orders + holds, hybrid Redis+DB lock, 15-min expiry), payment-service (gateways + idempotency +
  signed webhooks), and the required money/inventory unit tests. Seeder to provision the demo admin + sample
  vendor/attendee/events. Signed-URL endpoint for KYC document retrieval.

## 2026-06-27 — Day 2: Event + TicketType CRUD (core-api)
**Maps to:** Day 2 — `/crud Event`, `/crud TicketType` with ownership + lifecycle (PLAN.md); CLAUDE.md §A layering,
§F Event lifecycle / Ticket types.

**What changed**
- **Fixed the dangling binding (STEP 0):** `RepositoryServiceProvider` bound `EventRepositoryInterface` /
  `TicketTypeRepositoryInterface` to classes that didn't exist. Created the Contracts + Eloquent impls
  (`EventRepository`: `paginatePublished`/`paginateForVendor`/`paginateAll`/`create`/`update`/`delete`/
  `lockForUpdate`; `TicketTypeRepository`: `paginateForEvent`/`sumQuantityTotalForEvent`/`create`/`update`/`delete`)
  mirroring the User/Vendor/Attendee pattern — bindings now resolve.
- **Event CRUD** (Controller→Service→Repository): `EventController` (index/show/store/update/destroy),
  `EventService` (lifecycle + listing scope), `Store/UpdateEventRequest`, `EventResource` (status as
  `{value,label}`, datetimes UTC ISO-8601 + IANA `timezone`, `ticket_types` via `whenLoaded`).
- **TicketType CRUD** (nested under an event): `TicketTypeController`, `TicketTypeService`,
  `Store/UpdateTicketTypeRequest`, `TicketTypeResource`. Routes use **scoped bindings** so a ticket type must belong
  to the `{event}` (else 404).
- **Ownership via policies** (`EventPolicy`, `TicketTypePolicy`, auto-discovered): a vendor may only mutate events
  reachable through its own `vendor_id`; admin reads/writes all; **public (unauthenticated) index/show is limited to
  published/ongoing events** (drafts → 403 for non-owners). Public read routes carry no `auth` middleware, so the
  optional bearer user is resolved via `auth('sanctum')->user()` and authorized with `Gate::forUser(...)`.
- **Event lifecycle** (`EventStatus::canTransitionTo`): transitions enforced in `EventService`; illegal change →
  `InvalidEventTransitionException` (**409**, never a silent update). Publishing additionally requires the vendor's
  KYC to be `verified` → `VendorNotVerifiedException` (**422**).
- **Capacity invariant** (the critical one): `SUM(ticket_types.quantity_total) <= events.capacity` enforced on
  create **and** update **inside a `DB::transaction` under `Event::lockForUpdate()`** (re-read the row + recompute the
  sum in-txn) so concurrent edits can't bypass it → `EventCapacityExceededException` (**422**). Also forbids
  `quantity_total < quantity_sold` → `QuantityBelowSoldException` (**422**).
- **Validation:** IANA timezone (`Rule::in(DateTimeZone::listIdentifiers())`), `starts_at < ends_at`, `capacity >= 1`;
  ticket-type `price` integer minor units + 3-char `currency`, `group_discount` required-with `group_size` and a
  fraction in `[0,1)` (`min:0`,`lt:1`), `sales_start < sales_end`.
- **Infra:** added `read` (120/min) + `write` (40/min) named throttle limiters; added `AuthorizesRequests` to the
  base `Controller` (Laravel 11 ships it bare); new lang keys (`events.listed/retrieved`,
  `ticket_types.listed/retrieved/quantity_below_sold`, a `validation.*` group).
- **Factories:** `EventFactory` (states `draft/published/ongoing/completed/cancelled`, `forVendor()`; defaults to a
  **verified** vendor so events are publishable) and `TicketTypeFactory` (states `vip/earlyBird/groupBundle`,
  `forEvent()`). No secrets/PII — `[PLACEHOLDER]` only.

**Decisions (this session)**
- **Invalid lifecycle transition → 409** (state conflict); **publish-without-verified-KYC → 422** (unmet business
  precondition); **capacity / below-sold → 422**. All are domain `HttpException`s flowing through the global handler.
- **Vendor's own index returns all their events; public index returns published only; admin all.** Show allows
  public for published/ongoing; drafts/terminal states are owner/admin-only.
- **Event-capacity reduction below the existing ticket-type sum is NOT yet blocked on event update** (the invariant
  is enforced on the ticket-type side per the task). *Flagged as a follow-up* — lowering `events.capacity` could
  momentarily violate the sum; worth a guard when event update grows.

**Verification**
- `composer format` (Pint) — clean.
- `php artisan test` (sqlite `:memory:`, `RefreshDatabase`) → **53 passed (140 assertions)**; Auth suite still green.
  New: `Events\EventTest` (20) + `Events\TicketTypeTest` (18) cover happy/422/401/403(cross-vendor)/404 per action,
  invalid lifecycle transition (409), publish KYC gate, public index hides non-published, capacity-exceeded on
  create+update, and quantity-below-sold.

**Next**
- Vendor onboarding/KYC submission + admin review endpoints; then Day 3 (checkout holds + distributed lock,
  payment-service, webhooks). Seeder to provision the demo admin + sample vendor/attendee/events.

## 2026-06-27 — Day 2: Token auth + role onboarding (core-api)
**Maps to:** Day 2 — "Auth: Sanctum, `role` enum, `EnsureRole` middleware, registration/login" (PLAN.md);
CLAUDE.md §F Roles & auth.

**What changed**
- **Auth endpoints under `/api/v1/auth`** (Controller→Service→Repository, FormRequest validation, `ApiResponse`
  envelope): `POST register`, `POST login` (both `throttle:auth` — the 10/min limiter), `POST logout`,
  `GET me` (both `auth:sanctum`). Tokens are Sanctum personal access tokens.
- **`AuthService`** holds the transaction boundary: `register()` creates the `user` **and** its matching
  `vendors`/`attendees` profile row in one `DB::transaction`, then issues a token; `login()` verifies via
  `Hash::check` and throws `InvalidCredentialsException` (→ 401) without revealing which field failed;
  `logout()` revokes only the current access token; token issuance centralised.
- **Repository layer:** `UserRepository` (`create`, `findByEmail`), `VendorRepository`/`AttendeeRepository`
  (`createForUser`) behind interfaces, bound in `RepositoryServiceProvider` (alongside the pre-declared
  Event/TicketType bindings). Services depend on interfaces only.
- **HTTP layer:** `RegisterRequest` (name/email-unique/password-confirmed+`Password::defaults()`/role;
  `business_name` required for vendors; email normalised), `LoginRequest`; `UserResource` (+ `VendorResource`,
  `AttendeeResource`) — enums emitted as `{value,label}`, ISO-8601 timestamps, profile via `whenLoaded`.
- **Factories (deferred from the schema task):** `UserFactory` gained a `role` default + `admin()`/`vendor()`/
  `attendee()` states; new `VendorFactory` (with `verified()`/`rejected()`) and `AttendeeFactory`. All KYC/PII
  fields use demo-safe `[PLACEHOLDER]` values — no real NID/TIN/bank data. These back the auth tests and seeders.
- **Config wiring:** added the **`sanctum` guard** to `config/auth.php` (the scaffold only had `web`, so
  `auth:sanctum` would have thrown "guard not defined"); set `phpunit.xml` to `sqlite :memory:` so the suite runs
  without an external DB. Added an `admin/ping` route (role-gated) as the placeholder real admin endpoints join.
- **Lang:** added `auth.me`, `auth.role_not_self_assignable`, `auth.business_name_required` to `lang/en/api.php`.

**Decisions (this session)**
- **Public registration is limited to `vendor`/`attendee`; `admin` is rejected at validation (422).** Admins are
  provisioned by seeder/console only — a public endpoint that mints admins is a privilege-escalation hole. The
  task listed admin among roles, but security-first wins; the `EnsureRole` test uses a factory-made admin.
  *(Flag: confirm the seeder provisions the demo admin.)*
- **`VendorResource` deliberately omits all encrypted KYC/PII** (`tin_bin`, `representative_nid`,
  `payout_account`, `webhook_secret`); those are never returned by the API. Admin KYC review will use dedicated,
  audited endpoints + signed URLs.
- **Login failures return a single generic 401** (same message for unknown-email and wrong-password) to avoid
  user-enumeration.

**Verification**
- `composer format` (Pint) — **clean** (`{"result":"passed"}`).
- `php artisan test --filter=Auth` → **15 passed (64 assertions)**: register happy paths (attendee + vendor with
  profile/pending-KYC), 422 (missing fields, duplicate email, vendor without business_name, admin-role rejected),
  401 (wrong password, unknown email), `me` (auth + 401 when unauthenticated), logout revokes token, and
  **`EnsureRole` blocks an attendee from `/admin/ping` (403)** while an admin gets 200.
- Full suite `php artisan test` → **17 passed (66 assertions)**. Tests run on `sqlite :memory:` via `RefreshDatabase`.

**Next**
- `/crud Event` + `/crud TicketType` (ownership + lifecycle), vendor onboarding/KYC submission + admin review
  endpoints, then their feature tests. Seeder to provision the demo admin + sample vendor/attendee logins.

## 2026-06-27 — Day 2: Document PHP 8.4 runtime requirement (docs only)
**Maps to:** Day 2 setup-instruction accuracy. No code/migrations touched.

**What changed**
- Made the **PHP 8.4.1+** requirement explicit wherever setup is described, since the committed `composer.lock`
  resolves Symfony 8.x (`php >= 8.4.1`) even though `composer.json` allows `^8.2`. Chose to keep 8.4 and document it
  (vs. re-locking to Symfony ^7 for true 8.2 portability).
  - `README.md` — note in the Local/Laragon fallback section (8.2/8.3 fail `composer install`; docker `php:8.4-cli`
    needs no local PHP).
  - `CLAUDE.md` §5 — added a "PHP version" line to the run instructions.
  - `services/core-api/CLAUDE.md` — header `PHP 8.2+ → 8.4+` plus a runtime note.
- `docs/erd.md` — added `payouts.currency` to the ERD so the diagram matches the migration (honors "always record
  currency"; deviation was flagged in the schema task).

**Verification**
- Docs-only; no tests/formatter. Confirmed `composer.lock` contains `symfony/*` entries requiring `php >= 8.4.1`
  and the two Dockerfiles already pin `php:8.4-cli`.

## 2026-06-27 — Day 2: Docker verification (schema migrates on docker MySQL)
**Maps to:** Day 2 — "docker-compose boots mysql + redis + both Laravel services; health checks pass" (PLAN.md).
Proves the already-verified migrations apply on the **docker `mysql` host**, not just local Laragon.

**What changed (docker/.env wiring only — migrations untouched)**
- **`services/core-api/Dockerfile` + `services/payment-service/Dockerfile`:** bumped base image `php:8.3-cli →
  `php:8.4-cli`. The committed `composer.lock` was resolved on PHP 8.4, so `symfony/*` v8.1.1 (requires
  `php >=8.4.1`) failed `composer install` on the 8.3 image. `composer.json` allows `^8.2`; Laravel 11 supports 8.4.
- **`docker-compose.yml`:** mysql host-port mapping `"3306:3306"` → `"${MYSQL_HOST_PORT:-3307}:3306"` to avoid a
  clash with the host's Laragon MySQL on 3306. Inter-container traffic is unaffected (core-api always reaches
  `mysql:3306` on the compose network); only the host-side published port changed.

**Verification (docker)**
- `docker compose up -d --build mysql redis core-api` → `docker compose ps`: **mysql healthy, redis healthy,
  core-api up** (core-api has no healthcheck defined, so it reports "Up", which is its healthy state). Ports:
  mysql `3307→3306`, redis `6379`, core-api `8000`.
- `docker compose exec core-api php artisan migrate:fresh` → **24/24 migrations DONE, 0 errors** (4 base + 20
  domain) against the docker host. Tinker confirmed `host=mysql db=eventhub_core server_version=8.0.46
  migrations=24 tables=30` — i.e. the MySQL 8.0 container, not Laragon.
- `GET http://localhost:8000/api/v1/health` → **HTTP 200**, envelope
  `{"success":true,"data":{"service":"core-api","status":"ok"},"message":"core-api is healthy.","errors":null}`,
  with a `Log-Trace-ID` response header (trace middleware working).

**Next**
- Same as below (Day 2 continuation): repositories + Sanctum auth + `/crud Event`/`TicketType` + KYC endpoints +
  feature tests. When `payment-service` is scaffolded, bring it up too (Dockerfile already on php:8.4).

## 2026-06-27 — Day 2: Domain schema + Eloquent models (core-api)
**Maps to:** Day 2 — Scaffold + schema + core CRUD (PLAN.md) — the migrations/models slice.

**What changed**
- **20 domain migrations** added under `services/core-api/database/migrations/` (`2026_06_29_100001..100020`),
  one per entity group, in FK-dependency order: `vendors`, `attendees`, `kyc_documents`, `events`,
  `ticket_types`, `orders`, `order_items`, `ticket_holds`, `tickets`, `payments`, `refunds`, `payouts`,
  `payout_items`, `disputes`, `waitlist_entries`, `ledger_entries`, `idempotency_keys`, `settings`,
  `event_reminders`, `sales_reports`. `users` already carried ULID + `role` + soft-deletes from the scaffold
  (left as-is). All PKs are ULIDs (`$table->ulid('id')->primary()`); FKs use `foreignUlid`.
- **All ERD "Indexing strategy" indexes created with their exact names** (e.g. `idx_events_status_starts_at`,
  `idx_holds_type_status`, `idx_holds_status_expires_at`, `idx_ledger_vendor_created`, `idx_ledger_subject`,
  `idx_waitlist_type_status_pos`, `idx_payouts_vendor_status`, plus the `unique(...)` guards on
  `orders/payments/payouts.idempotency_key`, `tickets.qr_code`, `event_reminders(event_id,type)`,
  `sales_reports(report_date,vendor_id)`, `settings.key`, `idempotency_keys.key`). Named single-column FK
  indexes are created *before* the FK so one index backs both (no duplicates).
- **20 Eloquent models** created (+ `User` updated): `HasUlids` everywhere; `casts()` for every enum
  (reusing `app/Enums/*`), money as `integer` minor units, rates as `decimal:4`, JSON/array, and datetimes.
  Relationships match the ERD (incl. the second `users→vendors` reviewer link, polymorphic-subject note on
  `LedgerEntry`, `Refund hasOne Dispute`). `$fillable` set explicitly on every model (no mass-assignment holes).
- **PII handling wired into the models:** `vendors.tin_bin`, `representative_nid`, `webhook_secret` →
  `encrypted`; `payout_account` → `encrypted:array`; `kyc_documents.storage_path` → `encrypted`; all added to
  `$hidden`. Values used `[PLACEHOLDER]` only — no real secrets/PII.
- **Soft-delete policy applied exactly per ERD:** `SoftDeletes` on `users/vendors/attendees/events/`
  `ticket_types/kyc_documents` only. Financial/issued-artifact tables (`orders`, `payments`, `refunds`,
  `payouts`, `payout_items`, `ledger_entries`, `tickets`, `disputes`, `event_reminders`) are never deleted.
- **`ledger_entries` is strictly append-only:** `created_at` only (no `updated_at` column), model sets
  `const UPDATED_AT = null`; `amount` is a SIGNED `bigInteger`. `tickets` has `$timestamps = false` (no
  timestamp columns per ERD). **Vendor balance is derived** via `Vendor::balance()` = `SUM(ledger.amount)` —
  no balance column exists.
- **Config:** added `format` (Pint) and `test` scripts to `services/core-api/composer.json` (the scaffold
  hadn't defined `composer format` referenced by CLAUDE.md).

**Decisions (this session)**
- **Money columns are integer minor units** (`unsignedBigInteger`, signed `bigInteger` only for the ledger),
  rates `decimal(5,4)`; every money/rate table also stores `currency` (default `BDT`) — consistent with the
  poisha convention. *Note:* added `payouts.currency` (the ERD table omitted it) to honour the "always record
  currency" rule.
- **Named-index-before-FK pattern** so a single named index backs the FK (avoids MySQL's duplicate auto-index).
- **`sales_reports` NULL-platform-row caveat** left to app logic (`updateOrCreate`), per ERD — the composite
  unique only guards vendor-scoped rows in MySQL.
- **`idempotency_key` columns are non-null unique** (every order/charge/payout carries one).

**Verification**
- `php artisan migrate:fresh` applies **all 24 migrations** (4 base + 20 domain) cleanly against the dev DB.
  (Local run used Laragon MySQL on `127.0.0.1`; the committed `.env` targets the docker `mysql` host.)
- `composer format` (Pint) clean — 26 files auto-fixed (import ordering / factory-docblock FQCN), 0 remaining.
- **Tinker smoke test** (rolled back) confirmed: ULID PKs are 26 chars; `role`/`kyc_status` cast to enums;
  `tin_bin` is ciphertext at rest and decrypts back; `payout_account` round-trips as an array; `user->vendor`
  relation resolves; `Vendor::balance()` goes `0 → 4250` after sale(+5000)/commission(−750) ledger rows; the
  ledger row has `created_at` and **no** `updated_at`.

**Next**
- Continue **Day 2**: `RepositoryServiceProvider` bindings + repositories for `Event`/`TicketType`; Sanctum
  auth endpoints (register/login/logout/me) + `EnsureRole`-guarded route groups; `/crud Event`,
  `/crud TicketType` with ownership + lifecycle rules; vendor onboarding + KYC status endpoints. Then the
  required feature tests. Add model **factories** (referenced in `@use HasFactory` docblocks) when writing tests.

## 2026-06-26 — Day 1: Plan & architect (planning docs filled)
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

## 2026-06-26 — Day 0: AI command-center scaffold
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
