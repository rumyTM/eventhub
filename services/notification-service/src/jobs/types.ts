export type EmailNotificationType =
  | 'order.confirmation'
  | 'event.reminder'
  | 'payout.completed'
  | 'vendor.kyc_decision';

export type WebhookNotificationType =
  | 'order.created'
  | 'event.sold_out'
  | 'payout.sent';

export type NotificationType = EmailNotificationType | WebhookNotificationType;

export const EMAIL_NOTIFICATION_TYPES: ReadonlySet<EmailNotificationType> = new Set([
  'order.confirmation',
  'event.reminder',
  'payout.completed',
  'vendor.kyc_decision',
]);

export const WEBHOOK_NOTIFICATION_TYPES: ReadonlySet<WebhookNotificationType> = new Set([
  'order.created',
  'event.sold_out',
  'payout.sent',
]);

export interface Recipient {
  email?: string;
  name?: string;
  vendorId?: string;
}

export interface BaseJobPayload {
  type: NotificationType;
  idempotencyKey: string;
  trace_id: string;
  recipient: Recipient;
  data: Record<string, unknown>;
}

export interface EmailJobPayload extends BaseJobPayload {
  type: EmailNotificationType;
}

export interface WebhookJobPayload extends BaseJobPayload {
  type: WebhookNotificationType;
  url: string;
}

export type JobPayload = EmailJobPayload | WebhookJobPayload;

export function isEmailPayload(payload: JobPayload): payload is EmailJobPayload {
  return EMAIL_NOTIFICATION_TYPES.has(payload.type as EmailNotificationType);
}

export function isWebhookPayload(payload: JobPayload): payload is WebhookJobPayload {
  return WEBHOOK_NOTIFICATION_TYPES.has(payload.type as WebhookNotificationType);
}
