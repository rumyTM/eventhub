# payment-service — Payment Microservice (Laravel 11)

> A **separate, private** service that abstracts payment processing away from core-api. It knows about charges,
> refunds, and payouts — not about events or vendors beyond the IDs core-api passes. No endpoint here is publicly
> reachable. Root context: [`../../CLAUDE.md`](../../CLAUDE.md). Same layered conventions as core-api
> (Controller → Service → Repository → Model, `ApiResponse` envelope, global exception shaping) — see
> [`../core-api/CLAUDE.md`](../core-api/CLAUDE.md) §A–E for the shared standards; this file covers what's specific.
> `app/Support/ApiResponse.php` and `app/Helpers/LogHelper.php` are the **canonical stubs** copied verbatim from
> `.claude/stubs/laravel/` during `/scaffold-service` — do not fork them. Forward the incoming `Log-Trace-ID` header
> on every webhook callback to core-api so a charge can be traced end-to-end across both services.

---

## A. Responsibilities
1. **Simulate ≥2 gateways** — `StripeSimulator` and `PayPalSimulator`, each with a **configurable success/failure
   rate** (and optional latency) for testing.
2. **Create charge** — receive order details from core-api, start processing, return `pending` immediately.
3. **Webhook callback** — after a short (configurable) delay or immediately, call back into core-api with the final
   status (`success`/`failure`).
4. **Refunds** — full and partial. (The *policy* — 100/50/0% by time-to-event — is decided in core-api; this service
   executes the amount it's told and records it.)
5. **Payout execution** — receive a payout batch, process vendor settlements, report results back.
6. **Idempotency** — duplicate requests with the same `Idempotency-Key` never create duplicate charges/refunds/payouts.
7. **Auth** — shared-secret/token between core-api and this service. Nothing public.

## B. Gateway abstraction
- A `PaymentGatewayContract` interface (`charge`, `refund`, `payout`) in `app/Contracts/`.
- `StripeSimulator` and `PayPalSimulator` implement it; a `GatewayManager`/factory resolves by name.
- Success/failure rates from config/env (e.g. `GATEWAY_STRIPE_SUCCESS_RATE=0.9`). Simulator decides outcome via seeded
  randomness so tests can force success or failure deterministically (inject a fixed seed / a `FakeGateway` in tests).
- **Never** integrate a real gateway or store a real PAN/CVV/token. Simulated tokens/refs are clearly fake values.

## C. Endpoints (all behind shared-secret middleware, under `/api/v1`)
| Method | Path | Purpose | Idempotent on |
|---|---|---|---|
| POST | `/payments` | Create a charge for an order; returns `pending` + payment ref | `Idempotency-Key` |
| POST | `/refunds` | Full/partial refund of a payment | `Idempotency-Key` |
| POST | `/payouts` | Execute a payout batch | `Idempotency-Key` (per payout) |
| GET | `/payments/{ref}` | Status lookup | — |
| POST | `/simulate/advance` | (test/dev only) force a pending payment to resolve now | — |

Request/response shapes are defined in [`../../docs/system-architecture.md`](../../docs/system-architecture.md)
§API Contracts — **keep them in sync**.

## D. Idempotency (core requirement — unit tests required)
- `idempotency_keys` table: `key` (unique), `request_hash`, `response_payload`, `status`, timestamps.
- On every money-moving request: look up the key. If present and the request hash matches → return the stored
  response (HTTP + body) **without** re-charging. If present but the hash differs → 409 conflict. If absent → process
  inside a transaction, store key+result, then respond.
- The key is also the natural dedupe for webhook retries from core-api.

## E. Webhook callback to core-api
- After processing (with configurable delay via a queued job), POST the result to core-api's payment-webhook endpoint.
- Sign the body: `X-Signature: hmac_sha256(body, SHARED_SECRET)`. core-api verifies signature **and** bearer token.
- Webhook delivery is itself retried with backoff if core-api is unreachable; the charge result is persisted first so
  it's never lost.

## F. Auth between services
- Inbound: `EnsureServiceToken` middleware checks `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}` on every route.
- Outbound (to core-api): bearer token + HMAC signature.
- Tokens/secrets live in `.env` only — `[PLACEHOLDER]` in any example or fixture. No public route, ever.

## G. Money & audit
Store amounts as integer minor units (or `decimal:2`) + currency — never float. Every charge/refund/payout writes an
append-only `transactions` ledger row; status changes append, never overwrite financial history.

## H. Testing (required)
Pest, `RefreshDatabase`. Cover:
- **Idempotency:** same key twice → one charge, second returns the stored result; mismatched hash → 409.
- **Gateway outcomes:** forced success and forced failure (inject deterministic gateway), partial vs full refund math.
- **Auth:** request without/with wrong service token → 401/403; no route reachable unauthenticated.
- **Webhook:** core-api callback is signed and retried on failure (`Http::fake()`).

## I. Definition of done
Idempotency proven by test · no real PAN/token/secret anywhere (`[PLACEHOLDER]`) · `composer format` clean ·
shared-secret enforced on all routes · ledger append-only · `WORKLOG.md` updated.
