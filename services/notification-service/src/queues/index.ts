import { Queue } from 'bullmq';
import { env } from '../config/env';

// Pass a plain connection config so BullMQ uses its own bundled ioredis — avoids the
// "dual ioredis" TypeScript incompatibility caused by top-level ioredis vs bullmq's vendored copy.
function parseRedisUrl(url: string): { host: string; port: number } {
  const parsed = new URL(url);
  return { host: parsed.hostname, port: parseInt(parsed.port || '6379', 10) };
}

export function makeBullConnection(): { host: string; port: number; db: number; maxRetriesPerRequest: null } {
  return { ...parseRedisUrl(env.REDIS_URL), db: env.REDIS_DB, maxRetriesPerRequest: null };
}

export const notificationsQueue = new Queue(env.NS_NOTIFICATIONS_QUEUE, { connection: makeBullConnection() });
export const webhooksQueue = new Queue(env.NS_WEBHOOKS_QUEUE, { connection: makeBullConnection() });
export const deadLetterQueue = new Queue(env.DEAD_LETTER_QUEUE, { connection: makeBullConnection() });
