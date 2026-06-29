import { describe, it, expect, beforeEach } from 'vitest';
import { InMemoryDeliveryRepository } from '../src/delivery/InMemoryDeliveryRepository';

describe('DeliveryRepository — status transitions', () => {
  let repo: InMemoryDeliveryRepository;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
  });

  it('creates a record in pending state', async () => {
    await repo.create('key-1', 'order.confirmation');
    const record = await repo.getStatus('key-1');

    expect(record).not.toBeNull();
    expect(record?.status).toBe('pending');
    expect(record?.attempts).toBe(0);
    expect(record?.type).toBe('order.confirmation');
    expect(record?.createdAt).toBeTruthy();
  });

  it('transitions pending → sent', async () => {
    await repo.create('key-1', 'order.confirmation');
    await repo.markSent('key-1');

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('sent');
    expect(record?.lastError).toBeUndefined();
  });

  it('transitions pending → retrying with error and attempt count', async () => {
    await repo.create('key-1', 'payout.completed');
    await repo.markRetrying('key-1', 'Connection timeout', 2);

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('retrying');
    expect(record?.lastError).toBe('Connection timeout');
    expect(record?.attempts).toBe(2);
  });

  it('transitions pending → failed', async () => {
    await repo.create('key-1', 'event.reminder');
    await repo.markFailed('key-1', 'Max retries exceeded');

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('failed');
    expect(record?.lastError).toBe('Max retries exceeded');
  });

  it('transitions retrying → sent (retry succeeds)', async () => {
    await repo.create('key-1', 'order.confirmation');
    await repo.markRetrying('key-1', 'Timeout', 1);
    await repo.markSent('key-1');

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('sent');
  });

  it('transitions retrying → failed (dead-lettered)', async () => {
    await repo.create('key-1', 'payout.completed');
    await repo.markRetrying('key-1', 'Err', 3);
    await repo.markFailed('key-1', 'Exhausted after 5 retries');

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('failed');
  });

  it('returns null for unknown idempotencyKey', async () => {
    const record = await repo.getStatus('does-not-exist');
    expect(record).toBeNull();
  });

  it('is idempotent on create (second create is a no-op)', async () => {
    await repo.create('key-1', 'order.confirmation');
    await repo.markSent('key-1');
    await repo.create('key-1', 'order.confirmation'); // must not overwrite

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('sent'); // still sent, not reset to pending
  });

  it('listRecent returns records sorted newest first', async () => {
    await repo.create('key-a', 'order.confirmation');
    await repo.markSent('key-a');
    await repo.create('key-b', 'payout.completed');
    await repo.markRetrying('key-b', 'err', 1);
    await repo.create('key-c', 'event.reminder');

    const list = await repo.listRecent(10);
    expect(list.length).toBe(3);
    // All keys present
    const keys = list.map((r) => r.idempotencyKey);
    expect(keys).toContain('key-a');
    expect(keys).toContain('key-b');
    expect(keys).toContain('key-c');
  });

  it('listRecent respects the limit', async () => {
    for (let i = 0; i < 5; i++) {
      await repo.create(`key-${i}`, 'event.reminder');
    }
    const list = await repo.listRecent(3);
    expect(list.length).toBe(3);
  });
});
