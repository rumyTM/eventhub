import type Redis from 'ioredis';
import type { DeliveryRecord, DeliveryRepositoryInterface } from './types';

const INDEX_KEY = 'delivery:index';

export class DeliveryRepository implements DeliveryRepositoryInterface {
  constructor(private readonly redis: Redis) {}

  private recordKey(idempotencyKey: string): string {
    return `delivery:${idempotencyKey}`;
  }

  async create(idempotencyKey: string, type: string): Promise<void> {
    const key = this.recordKey(idempotencyKey);
    const exists = await this.redis.exists(key);
    if (exists) return;

    const record: DeliveryRecord = {
      idempotencyKey,
      type,
      status: 'pending',
      attempts: 0,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };

    const pipeline = this.redis.pipeline();
    pipeline.set(key, JSON.stringify(record));
    pipeline.zadd(INDEX_KEY, Date.now(), idempotencyKey);
    await pipeline.exec();
  }

  async getStatus(idempotencyKey: string): Promise<DeliveryRecord | null> {
    const raw = await this.redis.get(this.recordKey(idempotencyKey));
    if (!raw) return null;
    return JSON.parse(raw) as DeliveryRecord;
  }

  async markSent(idempotencyKey: string): Promise<void> {
    await this.patch(idempotencyKey, { status: 'sent' });
  }

  async markRetrying(idempotencyKey: string, error: string, attempts: number): Promise<void> {
    await this.patch(idempotencyKey, { status: 'retrying', lastError: error, attempts });
  }

  async markFailed(idempotencyKey: string, error: string): Promise<void> {
    const existing = await this.getStatus(idempotencyKey);
    await this.patch(idempotencyKey, {
      status: 'failed',
      lastError: error,
      attempts: existing ? existing.attempts + 1 : 1,
    });
  }

  async listRecent(limit = 50): Promise<DeliveryRecord[]> {
    const keys = await this.redis.zrevrange(INDEX_KEY, 0, limit - 1);
    if (keys.length === 0) return [];

    const values = await this.redis.mget(keys.map((k) => this.recordKey(k)));
    return values
      .filter((v): v is string => v !== null)
      .map((v) => JSON.parse(v) as DeliveryRecord);
  }

  private async patch(idempotencyKey: string, fields: Partial<DeliveryRecord>): Promise<void> {
    const existing = await this.getStatus(idempotencyKey);
    if (!existing) return;
    const updated: DeliveryRecord = { ...existing, ...fields, updatedAt: new Date().toISOString() };
    await this.redis.set(this.recordKey(idempotencyKey), JSON.stringify(updated));
  }
}
