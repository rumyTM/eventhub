import type Redis from 'ioredis';
import type { Queue } from 'bullmq';
import { logger } from '../lib/logger';
import { MAX_ATTEMPTS } from '../lib/backoff';
import type { JobPayload } from '../jobs/types';
import type { DeliveryRepositoryInterface } from '../delivery/types';

const JOB_OPTIONS = {
  attempts: MAX_ATTEMPTS,
  backoff: { type: 'custom' },
  removeOnComplete: { count: 1000 },
  removeOnFail: false,
} as const;

/**
 * Polls a Redis list (simple LPUSH source from core-api) using BLPOP,
 * then re-publishes each job into a BullMQ queue for retry-aware processing.
 *
 * Using idempotencyKey as the BullMQ jobId gives free queue-level dedup:
 * BullMQ won't add a second job with the same id while one is active or waiting.
 * Application-level dedup (DeliveryRepository) handles the case where the same
 * key arrives after the first job has already completed.
 */
export async function startIngress(
  redis: Redis,
  sourceListKey: string,
  bullQueue: Queue,
  deliveryRepo: DeliveryRepositoryInterface,
): Promise<void> {
  logger.info(`Ingress started`, { queue: sourceListKey });

  for (;;) {
    let raw: string | null = null;
    try {
      const result = await redis.blpop(sourceListKey, 30); // 30s timeout — loops without burning CPU
      if (!result) continue;

      raw = result[1];
      const payload = JSON.parse(raw) as JobPayload;

      // Application-level idempotency: skip if already delivered
      const existing = await deliveryRepo.getStatus(payload.idempotencyKey);
      if (existing?.status === 'sent') {
        logger.info('Ingress: skipping already-delivered notification', {
          idempotencyKey: payload.idempotencyKey,
          type: payload.type,
        });
        continue;
      }

      // Create the delivery record now so status is visible even if the worker hasn't started
      await deliveryRepo.create(payload.idempotencyKey, payload.type);

      await bullQueue.add(payload.type, payload, {
        ...JOB_OPTIONS,
        jobId: payload.idempotencyKey, // BullMQ dedup within queue
      });

      logger.info('Ingress: enqueued notification', {
        trace_id: payload.trace_id,
        type: payload.type,
        idempotencyKey: payload.idempotencyKey,
        queue: bullQueue.name,
      });
    } catch (err) {
      if (err instanceof SyntaxError) {
        logger.error('Ingress: invalid JSON in queue', { raw, error: (err as Error).message });
      } else {
        logger.error('Ingress error', { error: (err as Error).message, queue: sourceListKey });
        // Brief pause before retrying to avoid tight-looping on a persistent error
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }
  }
}
