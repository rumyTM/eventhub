import type { Channel, DeliveryResult } from '../../channels/Channel';
import type { EmailJobPayload } from '../types';
import type { DeliveryRepositoryInterface } from '../../delivery/types';
import { logger } from '../../lib/logger';

/**
 * Process an email notification job. Extracted from the BullMQ worker processor
 * so it can be unit-tested without a real queue.
 *
 * Returns `skipped: true` when the idempotencyKey was already delivered.
 * Throws on channel failure so BullMQ can schedule a retry.
 */
export async function processEmailJob(
  payload: EmailJobPayload,
  channel: Channel,
  deliveryRepo: DeliveryRepositoryInterface,
): Promise<DeliveryResult> {
  const { idempotencyKey, type, trace_id } = payload;
  const log = logger.child({ trace_id, type, idempotencyKey });

  const existing = await deliveryRepo.getStatus(idempotencyKey);
  if (existing?.status === 'sent') {
    log.info('Skipping duplicate email notification');
    return { success: true, skipped: true };
  }

  try {
    const result = await channel.send(payload);
    await deliveryRepo.markSent(idempotencyKey);
    log.info('Email notification delivered');
    return result;
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    log.error('Email notification delivery failed', { error: message });
    throw err;
  }
}
