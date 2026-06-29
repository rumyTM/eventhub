import { describe, it, expect, vi, beforeEach } from 'vitest';
import { computeBackoffDelay, MAX_ATTEMPTS } from '../src/lib/backoff';
import { handleJobFailure } from '../src/lib/jobFailure';
import { InMemoryDeliveryRepository } from '../src/delivery/InMemoryDeliveryRepository';
import type { Queue } from 'bullmq';

describe('Backoff strategy', () => {
  it('produces the sequence 1s / 4s / 16s / 64s / 256s', () => {
    const delays = [1, 2, 3, 4, 5].map(computeBackoffDelay);
    expect(delays).toEqual([1_000, 4_000, 16_000, 64_000, 256_000]);
  });

  it('clamps attempt 0 to the same as attempt 1 (1s)', () => {
    expect(computeBackoffDelay(0)).toBe(1_000);
  });

  it('MAX_ATTEMPTS is 6 (1 initial + 5 retries)', () => {
    expect(MAX_ATTEMPTS).toBe(6);
  });
});

describe('handleJobFailure — dead-letter after max retries', () => {
  let repo: InMemoryDeliveryRepository;
  let mockDlq: Pick<Queue, 'add'>;

  beforeEach(() => {
    repo = new InMemoryDeliveryRepository();
    mockDlq = { add: vi.fn().mockResolvedValue({}) };
  });

  it('moves job to dead-letter queue when attemptsMade >= maxAttempts', async () => {
    await repo.create('key-1', 'order.confirmation');

    await handleJobFailure(
      { idempotencyKey: 'key-1', attemptsMade: 6, maxAttempts: 6, jobData: {}, jobName: 'order.confirmation' },
      new Error('Connection refused'),
      mockDlq,
      repo,
    );

    expect(mockDlq.add).toHaveBeenCalledOnce();
    const args = vi.mocked(mockDlq.add).mock.calls[0];
    expect(args[0]).toBe('dl:order.confirmation');
    expect((args[1] as Record<string, string>).idempotencyKey).toBe('key-1');
    expect((args[1] as Record<string, string>).error).toBe('Connection refused');

    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('failed');
    expect(record?.lastError).toBe('Connection refused');
  });

  it('marks retrying (not dead-letter) on intermediate failure', async () => {
    await repo.create('key-1', 'order.confirmation');

    await handleJobFailure(
      { idempotencyKey: 'key-1', attemptsMade: 3, maxAttempts: 6, jobData: {}, jobName: 'order.confirmation' },
      new Error('Timeout'),
      mockDlq,
      repo,
    );

    expect(mockDlq.add).not.toHaveBeenCalled();
    const record = await repo.getStatus('key-1');
    expect(record?.status).toBe('retrying');
    expect(record?.attempts).toBe(3);
  });

  it('dead-letters after exactly the 5th retry (6th attempt)', async () => {
    await repo.create('key-dlq', 'payout.completed');

    // Simulate 5 intermediate failures → retrying
    for (let attempt = 1; attempt <= 5; attempt++) {
      await handleJobFailure(
        { idempotencyKey: 'key-dlq', attemptsMade: attempt, maxAttempts: 6, jobData: {}, jobName: 'payout.completed' },
        new Error(`Attempt ${attempt} failed`),
        mockDlq,
        repo,
      );
    }
    expect(mockDlq.add).not.toHaveBeenCalled();
    expect((await repo.getStatus('key-dlq'))?.status).toBe('retrying');

    // 6th attempt → dead-letter
    await handleJobFailure(
      { idempotencyKey: 'key-dlq', attemptsMade: 6, maxAttempts: 6, jobData: {}, jobName: 'payout.completed' },
      new Error('Final failure'),
      mockDlq,
      repo,
    );
    expect(mockDlq.add).toHaveBeenCalledOnce();
    expect((await repo.getStatus('key-dlq'))?.status).toBe('failed');
  });
});
