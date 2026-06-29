import type { JobPayload } from '../jobs/types';

export interface DeliveryResult {
  success: boolean;
  skipped?: boolean;
  error?: string;
}

export interface Channel {
  send(payload: JobPayload): Promise<DeliveryResult>;
}
