function required(name: string): string {
  const val = process.env[name];
  if (!val) throw new Error(`Missing required env var: ${name}`);
  return val;
}

function optional(name: string, fallback: string): string {
  return process.env[name] ?? fallback;
}

export const env = {
  NODE_ENV: optional('NODE_ENV', 'development'),
  PORT: parseInt(optional('PORT', '8002'), 10),
  LOG_LEVEL: optional('LOG_LEVEL', 'info'),

  REDIS_URL: optional('REDIS_URL', 'redis://localhost:6379'),
  REDIS_DB: parseInt(optional('REDIS_DB', '0'), 10),

  // Signing secret for vendor webhook delivery — must not appear in logs
  get VENDOR_WEBHOOK_SECRET(): string {
    return required('VENDOR_WEBHOOK_SECRET');
  },

  // Ingress queue names (core-api publishes here as plain Redis lists)
  NOTIFICATIONS_QUEUE: optional('NOTIFICATIONS_QUEUE', 'eventhub:notifications'),
  WEBHOOKS_QUEUE: optional('WEBHOOKS_QUEUE', 'eventhub:webhooks'),
  // BullMQ dead-letter queue (BullMQ v5 forbids ':' in queue names)
  DEAD_LETTER_QUEUE: optional('DEAD_LETTER_QUEUE', 'eventhub-dead-letter'),

  // Internal BullMQ processing queues (BullMQ v5 forbids ':' in queue names)
  NS_NOTIFICATIONS_QUEUE: optional('NS_NOTIFICATIONS_QUEUE', 'ns-notifications'),
  NS_WEBHOOKS_QUEUE: optional('NS_WEBHOOKS_QUEUE', 'ns-webhooks'),

  get isProduction(): boolean {
    return this.NODE_ENV === 'production';
  },
} as const;
