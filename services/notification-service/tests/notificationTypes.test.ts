import { describe, it, expect, vi, beforeEach } from 'vitest';
import { processEmailJob } from '../src/jobs/handlers/emailHandlers';
import { processWebhookJob } from '../src/jobs/handlers/webhookHandlers';
import {
  EMAIL_NOTIFICATION_TYPES,
  WEBHOOK_NOTIFICATION_TYPES,
  isEmailPayload,
  isWebhookPayload,
} from '../src/jobs/types';
import { InMemoryDeliveryRepository } from '../src/delivery/InMemoryDeliveryRepository';
import type { Channel } from '../src/channels/Channel';
import type { EmailJobPayload, WebhookJobPayload } from '../src/jobs/types';

// ── Type-set completeness ──────────────────────────────────────────────────────

describe('EmailNotificationType set includes new types', () => {
  it('includes vendor.kyc_decision', () => {
    expect(EMAIL_NOTIFICATION_TYPES.has('vendor.kyc_decision')).toBe(true);
  });

  it('existing types are still present', () => {
    expect(EMAIL_NOTIFICATION_TYPES.has('order.confirmation')).toBe(true);
    expect(EMAIL_NOTIFICATION_TYPES.has('event.reminder')).toBe(true);
    expect(EMAIL_NOTIFICATION_TYPES.has('payout.completed')).toBe(true);
  });
});

describe('WebhookNotificationType set includes all vendor-webhook types', () => {
  it('includes order.created', () => {
    expect(WEBHOOK_NOTIFICATION_TYPES.has('order.created')).toBe(true);
  });

  it('includes event.sold_out', () => {
    expect(WEBHOOK_NOTIFICATION_TYPES.has('event.sold_out')).toBe(true);
  });

  it('includes payout.sent', () => {
    expect(WEBHOOK_NOTIFICATION_TYPES.has('payout.sent')).toBe(true);
  });
});

// ── Type guards route correctly ────────────────────────────────────────────────

describe('isEmailPayload / isWebhookPayload type guards', () => {
  it('vendor.kyc_decision is recognised as an email payload', () => {
    const p = {
      type: 'vendor.kyc_decision',
      idempotencyKey: 'k',
      trace_id: 't',
      recipient: {},
      data: {},
    } as EmailJobPayload;
    expect(isEmailPayload(p)).toBe(true);
    expect(isWebhookPayload(p as never)).toBe(false);
  });

  it('order.created is recognised as a webhook payload', () => {
    const p = {
      type: 'order.created',
      idempotencyKey: 'k',
      trace_id: 't',
      recipient: {},
      data: {},
      url: 'https://vendor.example.com/hook',
    } as WebhookJobPayload;
    expect(isWebhookPayload(p)).toBe(true);
    expect(isEmailPayload(p as never)).toBe(false);
  });

  it('event.sold_out is recognised as a webhook payload', () => {
    const p = {
      type: 'event.sold_out',
      idempotencyKey: 'k',
      trace_id: 't',
      recipient: {},
      data: {},
      url: 'https://vendor.example.com/hook',
    } as WebhookJobPayload;
    expect(isWebhookPayload(p)).toBe(true);
  });

  it('payout.sent is recognised as a webhook payload', () => {
    const p = {
      type: 'payout.sent',
      idempotencyKey: 'k',
      trace_id: 't',
      recipient: {},
      data: {},
      url: 'https://vendor.example.com/hook',
    } as WebhookJobPayload;
    expect(isWebhookPayload(p)).toBe(true);
  });
});

// ── Handler processes each new type end-to-end ────────────────────────────────

function makeEmailPayload(type: 'vendor.kyc_decision', idempotencyKey: string): EmailJobPayload {
  return {
    type,
    idempotencyKey,
    trace_id: 'trace-kyc',
    recipient: { email: 'vendor@example.com', name: 'ACME Events', vendorId: 'v-01' },
    data: { vendorId: 'v-01', decision: 'approved' },
  };
}

function makeWebhookPayload(
  type: 'order.created' | 'event.sold_out' | 'payout.sent',
  idempotencyKey: string,
): WebhookJobPayload {
  return {
    type,
    idempotencyKey,
    trace_id: 'trace-wh',
    recipient: { vendorId: 'v-01' },
    url: 'https://vendor.example.com/webhook',
    data: { example: true },
  };
}

describe('processEmailJob handles vendor.kyc_decision', () => {
  let repo: InMemoryDeliveryRepository;
  let mockChannel: Channel;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
    mockChannel = { send: vi.fn().mockResolvedValue({ success: true }) };
  });

  it('delivers the notification and marks it sent', async () => {
    const payload = makeEmailPayload('vendor.kyc_decision', 'kyc-idem-1');
    await repo.create(payload.idempotencyKey, payload.type);

    const result = await processEmailJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(mockChannel.send).toHaveBeenCalledWith(payload);
    expect(result.success).toBe(true);
    expect((await repo.getStatus('kyc-idem-1'))?.status).toBe('sent');
  });

  it('skips duplicate delivery for the same idempotencyKey', async () => {
    const payload = makeEmailPayload('vendor.kyc_decision', 'kyc-idem-dup');
    await repo.create(payload.idempotencyKey, payload.type);

    await processEmailJob(payload, mockChannel, repo);
    const second = await processEmailJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(second.skipped).toBe(true);
  });
});

describe('processWebhookJob handles vendor webhook types', () => {
  let repo: InMemoryDeliveryRepository;
  let mockChannel: Channel;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
    mockChannel = { send: vi.fn().mockResolvedValue({ success: true }) };
  });

  it.each([
    ['order.created', 'wh-order-1'],
    ['event.sold_out', 'wh-soldout-1'],
    ['payout.sent', 'wh-payout-1'],
  ] as const)('delivers %s webhook', async (type, key) => {
    const payload = makeWebhookPayload(type, key);
    await repo.create(payload.idempotencyKey, payload.type);

    const result = await processWebhookJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(mockChannel.send).toHaveBeenCalledWith(payload);
    expect(result.success).toBe(true);
    expect((await repo.getStatus(key))?.status).toBe('sent');
  });

  it('skips duplicate order.created webhook', async () => {
    const payload = makeWebhookPayload('order.created', 'wh-dup-1');
    await repo.create(payload.idempotencyKey, payload.type);

    await processWebhookJob(payload, mockChannel, repo);
    const second = await processWebhookJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(second.skipped).toBe(true);
  });
});
