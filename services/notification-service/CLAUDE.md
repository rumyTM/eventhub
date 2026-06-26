# notification-service — Notification Microservice (Node.js)

> A **queue-driven** service responsible for all outbound communication. core-api publishes jobs to Redis; this
> service consumes them, delivers (simulated email + real-ish vendor webhooks), retries with backoff, dead-letters on
> exhaustion, and tracks delivery status. Root context: [`../../CLAUDE.md`](../../CLAUDE.md).

---

## A. Stack & layout
- **Node.js (LTS) + TypeScript**, **BullMQ** over **Redis** for queues, Express for the small admin/health API.
- Email is **simulated** — write to a log file / console transport. **Do not require real SMTP.**
- Suggested structure:
```
src/
  index.ts                 # boot: start workers + http server
  config/                  # env, redis connection, backoff policy
  queues/                  # queue + worker definitions (notifications, webhooks, dead-letter)
  jobs/                    # one handler per notification type
  channels/                # email (simulated), vendorWebhook (HMAC-signed HTTP)
  delivery/                # delivery-status tracking (DB or Redis), repository
  http/                    # health + delivery-status endpoints (same {success,message,data} envelope)
  lib/                     # logger, hmac, retry
tests/                     # vitest/jest
```

## B. Job types (consumed from core-api)
**Email (simulated — log, don't send):**
- `order.confirmation` → attendee
- `event.reminder` → ticket holders, 24h before event
- `payout.completed` → vendor
- `vendor.kyc_decision` → vendor (approved / rejected)

**Vendor webhooks** (vendor registers a URL in core-api; payload delivered here):
- `order.created`, `event.sold_out`, `payout.sent`

Each job payload from core-api includes: `type`, `idempotencyKey`, `recipient`, `data`, `trace_id`, and (for webhooks)
the target `url`. Keep the payload schema in sync with core-api's `NotificationPublisherContract`.

**Log correlation:** read `trace_id` from the job payload and include it on every log line for that job (structured
field `trace_id`), so a notification is traceable under the **same** id as the core-api request that produced it.
When delivering a vendor webhook, forward it as the `Log-Trace-ID` header too.

## C. Queue, retry & dead-letter (core requirement)
- Consume jobs from a named Redis queue (e.g. `eventhub:notifications`, `eventhub:webhooks`).
- **Retry with exponential backoff:** delays `1s, 4s, 16s, 64s, 256s` (`delay = 4^(retry-1)`), **max 5 retries** =
  **6 total attempts incl. the initial** (BullMQ `attempts: 6`), then dead-letter.
- On exhaustion → move to a **dead-letter queue** and mark the notification `failed`; never lose the record.
- **Idempotent delivery:** dedupe on `idempotencyKey` so a re-published job doesn't double-send.

## D. Delivery tracking
Persist per-notification status (`pending → sent | retrying | failed`) with attempt count, last error, timestamps —
queryable via a small HTTP endpoint for the dashboard. Status updates can also be surfaced back to core-api if needed.

## E. Vendor webhook delivery
- Sign each delivery: `X-EventHub-Signature: hmac_sha256(body, vendorWebhookSecret)`.
- Treat non-2xx as failure → retry per backoff policy → dead-letter.
- Timeouts and connection errors are failures, not crashes — isolate per job.

## F. Conventions
- TypeScript strict mode; explicit types on job handlers and channel interfaces.
- A `Channel` interface (`send(payload): Promise<DeliveryResult>`) with `EmailChannel` (simulated) and
  `VendorWebhookChannel` implementations — mirrors the "swappable provider behind an interface" pattern.
- Structured JSON logging (job id, type, attempt, status). **Never log** recipient secrets, tokens, or full webhook
  signing secrets — redact; use `[PLACEHOLDER]` in examples.
- HTTP responses use the same `{ success, message, data }` envelope as the Laravel services.

## G. Testing (required)
vitest/jest. Cover:
- **Retry/backoff:** a failing delivery retries on the schedule (1/4/16/64/256s) and dead-letters after the 5th
  retry (the 6th total attempt).
- **Idempotency:** the same `idempotencyKey` delivered twice sends once.
- **Webhook signing:** payload is HMAC-signed; non-2xx → retry path.
- **Delivery tracking:** status transitions recorded correctly.
Fake outbound HTTP (nock/msw); use an in-memory/redis-mock for queue tests where practical.

## H. Definition of done
Backoff + max-retries + DLQ working and tested · idempotent delivery · delivery status tracked · no secrets logged ·
`npm run lint` + `npm test` clean · `WORKLOG.md` updated.
