import { Worker, type Job } from 'bullmq';
import { env } from '../config/env';
import { getRedis } from '../config/redis';
import { makeBullConnection } from './index';
import { logger } from '../lib/logger';
import { computeBackoffDelay, MAX_ATTEMPTS } from '../lib/backoff';
import { handleJobFailure } from '../lib/jobFailure';
import { EmailChannel } from '../channels/EmailChannel';
import { VendorWebhookChannel } from '../channels/VendorWebhookChannel';
import { DeliveryRepository } from '../delivery/DeliveryRepository';
import { processEmailJob } from '../jobs/handlers/emailHandlers';
import { processWebhookJob } from '../jobs/handlers/webhookHandlers';
import type { EmailJobPayload, WebhookJobPayload, JobPayload } from '../jobs/types';
import { deadLetterQueue } from './index';

// Re-export so callers can import from one place
export { handleJobFailure };

// Repo is lazy so tests can import this module without a live Redis connection
let _deliveryRepo: DeliveryRepository | null = null;
function getDeliveryRepo(): DeliveryRepository {
  if (!_deliveryRepo) _deliveryRepo = new DeliveryRepository(getRedis());
  return _deliveryRepo;
}

function makeWorker(queueName: string, processor: (job: Job<JobPayload>) => Promise<void>): Worker {
  // Pass a plain config object so BullMQ uses its own bundled ioredis — avoids dual-ioredis TS error.
  const worker = new Worker<JobPayload>(queueName, processor, {
    connection: makeBullConnection(),
    settings: { backoffStrategy: computeBackoffDelay },
  });

  worker.on('completed', (job) => {
    logger.info('Job completed', {
      trace_id: job.data.trace_id,
      type: job.data.type,
      jobId: job.id,
      attempts: job.attemptsMade,
    });
  });

  worker.on('failed', (job, err) => {
    if (!job) return;
    void handleJobFailure(
      {
        idempotencyKey: job.data.idempotencyKey,
        attemptsMade: job.attemptsMade,
        maxAttempts: MAX_ATTEMPTS,
        jobData: job.data,
        jobName: job.name,
      },
      err,
      deadLetterQueue,
      getDeliveryRepo(),
    );
  });

  return worker;
}

export function createNotificationsWorker(): Worker {
  const emailChannel = new EmailChannel();
  const repo = getDeliveryRepo();
  return makeWorker(env.NS_NOTIFICATIONS_QUEUE, async (job: Job<JobPayload>) => {
    await processEmailJob(job.data as EmailJobPayload, emailChannel, repo);
  });
}

export function createWebhooksWorker(): Worker {
  const webhookChannel = new VendorWebhookChannel(env.VENDOR_WEBHOOK_SECRET);
  const repo = getDeliveryRepo();
  return makeWorker(env.NS_WEBHOOKS_QUEUE, async (job: Job<JobPayload>) => {
    await processWebhookJob(job.data as WebhookJobPayload, webhookChannel, repo);
  });
}
