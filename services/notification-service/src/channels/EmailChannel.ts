import type { Channel, DeliveryResult } from './Channel';
import type { EmailJobPayload, JobPayload } from '../jobs/types';
import { logger } from '../lib/logger';

const EMAIL_TEMPLATES: Record<string, string> = {
  'order.confirmation': 'Your order has been confirmed. Thank you for your purchase!',
  'event.reminder': 'Reminder: your event is coming up in 24 hours.',
  'payout.completed': 'Your payout has been processed successfully.',
  'vendor.kyc_decision': 'Your KYC application has been reviewed.',
};

export class EmailChannel implements Channel {
  send(payload: JobPayload): Promise<DeliveryResult> {
    const emailPayload = payload as EmailJobPayload;
    const template = EMAIL_TEMPLATES[emailPayload.type] ?? 'Notification from EventHub.';

    // Email delivery is simulated — log to console/file, no real SMTP
    logger.info('Email delivered (simulated)', {
      trace_id: emailPayload.trace_id,
      type: emailPayload.type,
      to: emailPayload.recipient.email,
      recipientName: emailPayload.recipient.name,
      subject: template,
      dataKeys: Object.keys(emailPayload.data),
    });

    return Promise.resolve({ success: true });
  }
}
