import Redis from 'ioredis';
import { env } from './env';

let _redis: Redis | null = null;
let _ingressRedis: Redis | null = null;

export function getRedis(): Redis {
  if (!_redis) {
    _redis = new Redis(env.REDIS_URL, {
      db: env.REDIS_DB,
      maxRetriesPerRequest: null, // required by BullMQ
      lazyConnect: false,
    });
  }
  return _redis;
}

// Separate connection for blocking BLPOP (cannot be shared with BullMQ connection)
export function getIngressRedis(): Redis {
  if (!_ingressRedis) {
    _ingressRedis = new Redis(env.REDIS_URL, {
      db: env.REDIS_DB,
      maxRetriesPerRequest: null,
      lazyConnect: false,
    });
  }
  return _ingressRedis;
}

export async function closeRedis(): Promise<void> {
  await Promise.all([
    _redis?.quit(),
    _ingressRedis?.quit(),
  ]);
  _redis = null;
  _ingressRedis = null;
}
