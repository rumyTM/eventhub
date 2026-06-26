---
name: payment-service
description: Work on the EventHub payment microservice (Laravel) — simulated gateways (StripeSimulator/PayPalSimulator), charge creation, webhook callbacks, full/partial refunds, payout execution, and idempotency. Use when the task touches anything under services/payment-service, or involves charges, refunds, payout execution, gateway simulation, idempotency keys, or inter-service payment auth. Scopes you to the payment service.
---

# Skill: payment-service

**Service boundary:** `services/payment-service` only. A private Laravel service that abstracts payment processing.
It knows charges/refunds/payouts and gateway simulation — **not** EventHub domain rules. Refund *policy* (100/50/0%
by time-to-event) lives in core-api; this service executes the amount it is told. No endpoint is public.

## Become productive fast
1. Read `services/payment-service/CLAUDE.md` (this service's specifics) and `services/core-api/CLAUDE.md` sections
   A-E for the shared Laravel layering + `ApiResponse` envelope.
2. Read `../../docs/system-architecture.md` section "API Contracts" for the exact request/response of
   `/payments`, `/refunds`, `/payouts` and the webhook callback shape — **keep code in sync with it**.

## Key files & patterns
- `PaymentGatewayContract` (charge/refund/payout) + `StripeSimulator`, `PayPalSimulator`, resolved by a
  `GatewayManager`. Success/failure rate from config; deterministic seed / `FakeGateway` in tests.
- `idempotency_keys` table: key (unique), request_hash, response_payload, status. Same key + same hash -> return
  stored response (no re-charge); same key + different hash -> 409.
- Webhook callback to core-api is **signed**: `X-Signature: hmac_sha256(body, SHARED_SECRET)`, plus bearer token,
  retried with backoff (result persisted first so it's never lost).
- `EnsureServiceToken` middleware on every route. Amounts as integer minor units / `decimal:2` + currency, never
  float. Every charge/refund/payout appends to a `transactions` ledger (never overwrite history).

## How to run tests
```
cd services/payment-service
php artisan test --filter=Idempotency
php artisan test
composer format
```
Required coverage: idempotency (same key once; mismatched hash -> 409), forced gateway success/failure, partial vs
full refund math, auth rejection (401/403), signed + retried webhook (`Http::fake()`).

## Refuse / fix these
Any real PAN/CVV/token/secret (use `[PLACEHOLDER]`); a money endpoint without idempotency; a publicly reachable route;
float money; overwriting a ledger row instead of appending; webhook sent without signature.
