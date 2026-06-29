export type DeliveryStatus = 'pending' | 'sent' | 'retrying' | 'failed';

export interface DeliveryRecord {
  idempotencyKey: string;
  type: string;
  status: DeliveryStatus;
  attempts: number;
  lastError?: string;
  createdAt: string;
  updatedAt: string;
}

export interface DeliveryRepositoryInterface {
  create(idempotencyKey: string, type: string): Promise<void>;
  getStatus(idempotencyKey: string): Promise<DeliveryRecord | null>;
  markSent(idempotencyKey: string): Promise<void>;
  markRetrying(idempotencyKey: string, error: string, attempts: number): Promise<void>;
  markFailed(idempotencyKey: string, error: string): Promise<void>;
  listRecent(limit?: number): Promise<DeliveryRecord[]>;
}
