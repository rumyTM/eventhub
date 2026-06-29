import type { Queue } from 'bullmq';
import type { DeliveryRepositoryInterface } from '../delivery/types';
import { logger } from './logger';
import { MAX_ATTEMPTS } from './backoff';

/**
 * Handles a job failure: moves to dead-letter queue after max retries exhausted,
 * otherwise marks as retrying. Extracted from the worker for unit-testability.
 */
export async function handleJobFailure(
  params: {
    idempotencyKey: string;
    attemptsMade: number;
    maxAttempts: number;
    jobData: unknown;
    jobName: string;
  },
  err: Error,
  dlq: Pick<Queue, 'add'>,
  repo: DeliveryRepositoryInterface,
): Promise<void> {
  const { idempotencyKey, attemptsMade, maxAttempts, jobData, jobName } = params;

  if (attemptsMade >= maxAttempts) {
    await dlq.add(`dl:${jobName}`, {
      idempotencyKey,
      error: err.message,
      data: jobData,
      deadLetteredAt: new Date().toISOString(),
    });
    await repo.markFailed(idempotencyKey, err.message);
    logger.error('Job dead-lettered after max retries', {
      idempotencyKey,
      jobName,
      error: err.message,
    });
  } else {
    await repo.markRetrying(idempotencyKey, err.message, attemptsMade);
    logger.warn('Job will retry', {
      idempotencyKey,
      jobName,
      attemptsMade,
      error: err.message,
    });
  }
}

export { MAX_ATTEMPTS };
