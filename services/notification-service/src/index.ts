import { startServer } from './http/server';
import { notificationsQueue, webhooksQueue } from './queues/index';
import { createNotificationsWorker, createWebhooksWorker } from './queues/workers';
import { startIngress } from './queues/ingress';
import { getIngressRedis, closeRedis } from './config/redis';
import { DeliveryRepository } from './delivery/DeliveryRepository';
import { getRedis } from './config/redis';
import { logger } from './lib/logger';
import { env } from './config/env';

async function main(): Promise<void> {
  logger.info('Notification service starting', { env: env.NODE_ENV, port: env.PORT });

  const deliveryRepo = new DeliveryRepository(getRedis());

  const notificationsWorker = createNotificationsWorker();
  const webhooksWorker = createWebhooksWorker();
  logger.info('BullMQ workers started', {
    queues: [env.NS_NOTIFICATIONS_QUEUE, env.NS_WEBHOOKS_QUEUE],
  });

  const ingressRedis = getIngressRedis();
  void startIngress(ingressRedis, env.NOTIFICATIONS_QUEUE, notificationsQueue, deliveryRepo);
  void startIngress(ingressRedis, env.WEBHOOKS_QUEUE, webhooksQueue, deliveryRepo);
  logger.info('Ingress bridges started', {
    sources: [env.NOTIFICATIONS_QUEUE, env.WEBHOOKS_QUEUE],
  });

  startServer();

  // Block until a termination signal; the promise resolves on SIGTERM/SIGINT
  await new Promise<void>((resolve) => {
    async function shutdown(signal: string): Promise<void> {
      logger.info('Graceful shutdown initiated', { signal });
      await Promise.all([notificationsWorker.close(), webhooksWorker.close()]);
      await closeRedis();
      logger.info('Notification service stopped');
      resolve();
    }
    process.once('SIGTERM', () => void shutdown('SIGTERM'));
    process.once('SIGINT', () => void shutdown('SIGINT'));
  });
}

main().catch((err: unknown) => {
  const message = err instanceof Error ? err.message : String(err);
  logger.error('Fatal startup error', { error: message });
  process.exit(1);
});
