# Introduction

REST API for the EventHub multi-vendor event ticketing and payout platform. Vendors create events and sell tickets; attendees browse and buy; admins approve vendors, manage refunds, and disburse payouts. Every financial operation is auditable, idempotent, and resilient to partial failure.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

## Authentication

Most write endpoints and all admin endpoints require a Sanctum **bearer token**.
Obtain one by calling **POST /api/v1/auth/login** (or **register**); include it as:

```
Authorization: Bearer {YOUR_AUTH_KEY}
```

Public read endpoints (event catalog, ticket types) work without a token.

## Demo credentials (seeded)

| Role     | Email                        | Password |
|----------|------------------------------|----------|
| Admin    | admin@eventhub.test          | password |
| Vendor   | vendor@eventhub.test         | password |
| Attendee | attendee@eventhub.test       | password |

## Idempotency

Money-moving endpoints (checkout, refund, payout-execute) are idempotent.
Pass a unique `Idempotency-Key` header on the checkout request; duplicate calls return the
original result without re-executing the side effect.

## Response envelope

Every response uses the same shape:

```json
{
  "success": true,
  "message": "Human-readable summary",
  "data": {},
  "errors": null
}
```

Validation failures return HTTP 422 with `errors` as a field → messages map.
Rate-limited responses return HTTP 429 with `data.retry_after` (seconds).

<aside>Internal webhook endpoints (<code>/api/v1/internal/payments/*</code>) are excluded from
these docs — they are called only by the payment-service and are protected by a shared-secret
HMAC signature, never by user tokens.</aside>

