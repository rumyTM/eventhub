import { Queue } from 'bullmq';
import { getRedis } from '../config/redis';
import { env } from '../config/env';

const connection = { createClient: () => getRedis() };

export const notificationsQueue = new Queue(env.NS_NOTIFICATIONS_QUEUE, { connection });
export const webhooksQueue = new Queue(env.NS_WEBHOOKS_QUEUE, { connection });
export const deadLetterQueue = new Queue(env.DEAD_LETTER_QUEUE, { connection });

export { connection };
