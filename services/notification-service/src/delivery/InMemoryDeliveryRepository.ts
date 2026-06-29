import type { DeliveryRecord, DeliveryRepositoryInterface, DeliveryStatus } from './types';

export class InMemoryDeliveryRepository implements DeliveryRepositoryInterface {
  private readonly records = new Map<string, DeliveryRecord>();

  create(idempotencyKey: string, type: string): Promise<void> {
    if (!this.records.has(idempotencyKey)) {
      this.records.set(idempotencyKey, {
        idempotencyKey,
        type,
        status: 'pending',
        attempts: 0,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      });
    }
    return Promise.resolve();
  }

  getStatus(idempotencyKey: string): Promise<DeliveryRecord | null> {
    return Promise.resolve(this.records.get(idempotencyKey) ?? null);
  }

  markSent(idempotencyKey: string): Promise<void> {
    this.patch(idempotencyKey, { status: 'sent' });
    return Promise.resolve();
  }

  markRetrying(idempotencyKey: string, error: string, attempts: number): Promise<void> {
    this.patch(idempotencyKey, { status: 'retrying', lastError: error, attempts });
    return Promise.resolve();
  }

  markFailed(idempotencyKey: string, error: string): Promise<void> {
    const existing = this.records.get(idempotencyKey);
    this.patch(idempotencyKey, {
      status: 'failed',
      lastError: error,
      attempts: existing ? existing.attempts + 1 : 1,
    });
    return Promise.resolve();
  }

  listRecent(limit = 50): Promise<DeliveryRecord[]> {
    const sorted = [...this.records.values()]
      .sort((a, b) => b.updatedAt.localeCompare(a.updatedAt))
      .slice(0, limit);
    return Promise.resolve(sorted);
  }

  private patch(idempotencyKey: string, fields: Partial<DeliveryRecord>): void {
    const existing = this.records.get(idempotencyKey);
    if (!existing) return;
    this.records.set(idempotencyKey, {
      ...existing,
      ...fields,
      updatedAt: new Date().toISOString(),
    });
  }

  clear(): void {
    this.records.clear();
  }

  getAll(): DeliveryRecord[] {
    return [...this.records.values()];
  }
}

export function makeStatus(
  idempotencyKey: string,
  type: string,
  status: DeliveryStatus = 'pending',
): DeliveryRecord {
  return {
    idempotencyKey,
    type,
    status,
    attempts: 0,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}
