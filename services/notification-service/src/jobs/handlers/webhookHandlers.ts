import type { Channel, DeliveryResult } from '../../channels/Channel';
import type { WebhookJobPayload } from '../types';
import type { DeliveryRepositoryInterface } from '../../delivery/types';
import { logger } from '../../lib/logger';

/**
 * Process a vendor webhook job. Extracted from the BullMQ worker processor
 * so it can be unit-tested without a real queue.
 *
 * Returns `skipped: true` when the idempotencyKey was already delivered.
 * Throws on channel failure (non-2xx, timeout, network) so BullMQ retries.
 */
export async function processWebhookJob(
  payload: WebhookJobPayload,
  channel: Channel,
  deliveryRepo: DeliveryRepositoryInterface,
): Promise<DeliveryResult> {
  const { idempotencyKey, type, trace_id } = payload;
  const log = logger.child({ trace_id, type, idempotencyKey, url: payload.url });

  const existing = await deliveryRepo.getStatus(idempotencyKey);
  if (existing?.status === 'sent') {
    log.info('Skipping duplicate webhook notification');
    return { success: true, skipped: true };
  }

  try {
    const result = await channel.send(payload);
    await deliveryRepo.markSent(idempotencyKey);
    log.info('Vendor webhook delivered');
    return result;
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    log.warn('Vendor webhook delivery failed', { error: message });
    throw err;
  }
}
