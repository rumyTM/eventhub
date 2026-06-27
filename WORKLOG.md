# EventHub — Work Log

> Running, most-recent-first log of what changed, decisions made, verification, and what's next. Update at the end of
> every working session with `/update-worklog`. Significant decisions get promoted into
> [`docs/technical-decision-log.md`](./docs/technical-decision-log.md).

---

## 2026-06-29 — Day 3 (slice 1): Checkout — order + 15-min holds, distributed lock, idempotency (core-api)
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

## 2026-06-29 — Day 2: Vendor KYC review flow + capacity invariant closed (core-api)
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

## 2026-06-29 — Day 2: Event + TicketType CRUD (core-api)
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

## 2026-06-29 — Day 2: Token auth + role onboarding (core-api)
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

## 2026-06-29 — Day 2: Document PHP 8.4 runtime requirement (docs only)
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

## 2026-06-29 — Day 2: Docker verification (schema migrates on docker MySQL)
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

## 2026-06-29 — Day 2: Domain schema + Eloquent models (core-api)
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

## 2026-06-28 — Day 1: Plan & architect (planning docs filled)
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

## 2026-06-27 — Day 0: AI command-center scaffold
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
