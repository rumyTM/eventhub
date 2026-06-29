import winston from 'winston';
import { env } from '../config/env';

const transports: winston.transport[] = [
  new winston.transports.Console({
    format: env.isProduction
      ? winston.format.json()
      : winston.format.combine(
          winston.format.colorize(),
          winston.format.simple(),
        ),
  }),
];

if (env.isProduction) {
  transports.push(
    new winston.transports.File({
      filename: 'logs/notifications.log',
      maxsize: 10 * 1024 * 1024,
      maxFiles: 5,
    }),
  );
}

export const logger = winston.createLogger({
  level: env.LOG_LEVEL,
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.errors({ stack: true }),
    winston.format.json(),
  ),
  transports,
  silent: env.NODE_ENV === 'test',
});

export function childLogger(meta: Record<string, unknown>): winston.Logger {
  return logger.child(meta);
}
