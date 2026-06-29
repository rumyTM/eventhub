import { describe, it, expect, vi, beforeEach } from 'vitest';
import { processEmailJob } from '../src/jobs/handlers/emailHandlers';
import { processWebhookJob } from '../src/jobs/handlers/webhookHandlers';
import { InMemoryDeliveryRepository } from '../src/delivery/InMemoryDeliveryRepository';
import type { Channel } from '../src/channels/Channel';
import type { EmailJobPayload, WebhookJobPayload } from '../src/jobs/types';

function makeEmailPayload(idempotencyKey = 'idem-1'): EmailJobPayload {
  return {
    type: 'order.confirmation',
    idempotencyKey,
    trace_id: 'trace-abc',
    recipient: { email: 'attendee@example.com', name: 'Alice' },
    data: { orderId: 'order-01', eventName: 'Tech Conf 2025' },
  };
}

function makeWebhookPayload(idempotencyKey = 'idem-wh-1'): WebhookJobPayload {
  return {
    type: 'order.created',
    idempotencyKey,
    trace_id: 'trace-xyz',
    recipient: { vendorId: 'vendor-01', email: 'vendor@example.com' },
    url: 'https://vendor.example.com/webhook',
    data: { orderId: 'order-01', amount: 5000 },
  };
}

describe('Email job idempotency', () => {
  let repo: InMemoryDeliveryRepository;
  let mockChannel: Channel;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
    mockChannel = { send: vi.fn().mockResolvedValue({ success: true }) };
  });

  it('delivers on first call', async () => {
    const payload = makeEmailPayload();
    await repo.create(payload.idempotencyKey, payload.type);

    const result = await processEmailJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(result.success).toBe(true);
    expect(result.skipped).toBeUndefined();
    expect((await repo.getStatus(payload.idempotencyKey))?.status).toBe('sent');
  });

  it('skips delivery on duplicate idempotencyKey', async () => {
    const payload = makeEmailPayload();
    await repo.create(payload.idempotencyKey, payload.type);

    // First delivery
    await processEmailJob(payload, mockChannel, repo);
    // Second delivery (same key)
    const result = await processEmailJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce(); // only once
    expect(result.skipped).toBe(true);
  });

  it('different keys are delivered independently', async () => {
    const p1 = makeEmailPayload('key-A');
    const p2 = makeEmailPayload('key-B');
    await repo.create(p1.idempotencyKey, p1.type);
    await repo.create(p2.idempotencyKey, p2.type);

    await processEmailJob(p1, mockChannel, repo);
    await processEmailJob(p2, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledTimes(2);
  });

  it('rethrows on channel failure (so BullMQ can retry)', async () => {
    const payload = makeEmailPayload('fail-key');
    await repo.create(payload.idempotencyKey, payload.type);
    mockChannel = { send: vi.fn().mockRejectedValue(new Error('SMTP down')) };

    await expect(processEmailJob(payload, mockChannel, repo)).rejects.toThrow('SMTP down');
    // Status should not be 'sent' after failure
    expect((await repo.getStatus('fail-key'))?.status).not.toBe('sent');
  });
});

describe('Webhook job idempotency', () => {
  let repo: InMemoryDeliveryRepository;
  let mockChannel: Channel;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
    mockChannel = { send: vi.fn().mockResolvedValue({ success: true }) };
  });

  it('delivers webhook on first call', async () => {
    const payload = makeWebhookPayload();
    await repo.create(payload.idempotencyKey, payload.type);

    const result = await processWebhookJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(result.success).toBe(true);
  });

  it('skips webhook on duplicate idempotencyKey', async () => {
    const payload = makeWebhookPayload();
    await repo.create(payload.idempotencyKey, payload.type);

    await processWebhookJob(payload, mockChannel, repo);
    const second = await processWebhookJob(payload, mockChannel, repo);

    expect(mockChannel.send).toHaveBeenCalledOnce();
    expect(second.skipped).toBe(true);
  });
});
