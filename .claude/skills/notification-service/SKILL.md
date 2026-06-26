---
name: notification-service
description: Work on the EventHub notification microservice (Node.js + BullMQ + Redis) — simulated email, vendor webhook delivery, exponential-backoff retries, dead-letter queue, and delivery tracking. Use when the task touches anything under services/notification-service, or involves notification jobs, queue consumers, retry/backoff, DLQ, webhook signing, or delivery status. Scopes you to the notification service.
---

# Skill: notification-service

**Service boundary:** `services/notification-service` only. A queue-driven Node.js service that consumes notification
jobs published by core-api to Redis and delivers them. Email is **simulated** (log to file/console — never real SMTP).
It does not own domain data; it acts on the payloads core-api sends.

## Become productive fast
1. Read `services/notification-service/CLAUDE.md` (stack, job types, retry policy, layout).
2. Confirm the job payload schema matches core-api's `NotificationPublisherContract` (sections in core-api CLAUDE.md
   + `../../docs/system-architecture.md`). They must stay in sync.

## Key files & patterns
- BullMQ queues over Redis: `eventhub:notifications`, `eventhub:webhooks`, plus a dead-letter queue.
- Job types — email (simulated): `order.confirmation`, `event.reminder`, `payout.completed`, `vendor.kyc_decision`;
  vendor webhooks: `order.created`, `event.sold_out`, `payout.sent`.
- **Retry:** exponential backoff ~ `1s, 4s, 16s, 64s` (4^n), **max 5 attempts**, then dead-letter + mark `failed`.
- **Idempotent delivery:** dedupe on `idempotencyKey`. Vendor webhooks HMAC-signed
  (`X-EventHub-Signature`). Delivery status tracked per notification (`pending -> sent | retrying | failed`).
- `Channel` interface (`send(payload)`) with `EmailChannel` (sim) + `VendorWebhookChannel`. TypeScript strict.
  HTTP endpoints use the same `{success,message,data}` envelope. Never log secrets.

## How to run tests
```
cd services/notification-service
npm test                 # vitest/jest
npm run lint
```
Required coverage: retry/backoff schedule + stop at 5 -> DLQ; idempotent (same key sends once); webhook signing +
non-2xx retry; delivery-status transitions. Fake outbound HTTP (nock/msw).

## Refuse / fix these
Real SMTP dependency; losing a failed notification instead of dead-lettering; double-send on re-published job;
unsigned vendor webhook; secrets in logs.
