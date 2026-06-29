import axios, { AxiosError } from 'axios';
import type { Channel, DeliveryResult } from './Channel';
import type { JobPayload, WebhookJobPayload } from '../jobs/types';
import { signPayload } from '../lib/hmac';
import { logger } from '../lib/logger';

export class VendorWebhookChannel implements Channel {
  constructor(private readonly secret: string) {}

  async send(payload: JobPayload): Promise<DeliveryResult> {
    const webhookPayload = payload as WebhookJobPayload;

    if (!webhookPayload.url) {
      throw new Error(`Webhook job missing url: ${webhookPayload.type} / ${webhookPayload.idempotencyKey}`);
    }

    const body = JSON.stringify({
      type: webhookPayload.type,
      idempotencyKey: webhookPayload.idempotencyKey,
      data: webhookPayload.data,
    });

    const signature = signPayload(body, this.secret);

    try {
      const response = await axios.post(webhookPayload.url, body, {
        headers: {
          'Content-Type': 'application/json',
          'X-EventHub-Signature': signature,
          'Log-Trace-ID': webhookPayload.trace_id,
        },
        timeout: 10_000,
        // Treat any non-2xx as an error (axios throws by default on 4xx/5xx)
        validateStatus: (status) => status >= 200 && status < 300,
      });

      logger.info('Vendor webhook delivered', {
        trace_id: webhookPayload.trace_id,
        type: webhookPayload.type,
        url: webhookPayload.url,
        status: response.status,
      });

      return { success: true };
    } catch (err) {
      const status = err instanceof AxiosError ? err.response?.status : undefined;
      const message = err instanceof Error ? err.message : String(err);

      logger.warn('Vendor webhook delivery failed', {
        trace_id: webhookPayload.trace_id,
        type: webhookPayload.type,
        url: webhookPayload.url,
        status,
        error: message,
      });

      // Re-throw so BullMQ retries the job
      throw new Error(`Webhook to ${webhookPayload.url} failed (${status ?? 'network error'}): ${message}`);
    }
  }
}
