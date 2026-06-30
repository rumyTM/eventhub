<?php

namespace App\Contracts;

/**
 * core-api's publish-only view of the notification-service (CLAUDE.md §B).
 *
 * Jobs are written to plain Redis lists (LPUSH) using the queue names that the
 * notification-service monitors via BLPOP. The notification-service owns actual
 * delivery, retry, backoff, and DLQ logic — core-api only decides *what* to send.
 *
 * Payload schema (must stay in sync with notification-service `src/jobs/types.ts`):
 * {
 *   type:             string (EmailNotificationType | WebhookNotificationType)
 *   idempotencyKey:   string — must be globally unique per notification event
 *   trace_id:         string — the current request's Log-Trace-ID (from LogHelper)
 *   recipient:        { email?, name?, vendorId? }
 *   data:             Record<string, mixed> — job-type-specific payload
 *   url?:             string — required for webhook types only
 * }
 *
 * Never include raw card data, tokens, OTPs, or signing secrets in the payload.
 */
interface NotificationPublisherContract
{
    /**
     * Publish a simulated-email notification job.
     *
     * @param  string  $type  One of: order.confirmation, event.reminder,
     *                        payout.completed, vendor.kyc_decision
     * @param  array<string,mixed>  $recipient  { email?, name?, vendorId? }
     * @param  array<string,mixed>  $data  Job-type-specific payload fields
     * @param  string  $idempotencyKey  Unique key — same key → only one delivery
     */
    public function publishEmail(
        string $type,
        array $recipient,
        array $data,
        string $idempotencyKey,
    ): void;

    /**
     * Publish a vendor-webhook notification job.
     *
     * @param  string  $type  One of: order.created, event.sold_out, payout.sent
     * @param  string  $url  Vendor-registered webhook endpoint
     * @param  array<string,mixed>  $recipient  { email?, name?, vendorId? }
     * @param  array<string,mixed>  $data  Webhook body data fields
     * @param  string  $idempotencyKey  Unique key — same key → only one delivery
     */
    public function publishWebhook(
        string $type,
        string $url,
        array $recipient,
        array $data,
        string $idempotencyKey,
    ): void;
}
