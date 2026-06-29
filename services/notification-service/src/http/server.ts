import express from 'express';
import type { Express } from 'express';
import { buildRouter } from './routes';
import { logger } from '../lib/logger';
import { env } from '../config/env';

export function createServer(): Express {
  const app = express();
  app.use(express.json());
  app.use(buildRouter());
  return app;
}

export function startServer(): void {
  const app = createServer();
  app.listen(env.PORT, () => {
    logger.info(`Notification service HTTP server listening`, { port: env.PORT });
  });
}
