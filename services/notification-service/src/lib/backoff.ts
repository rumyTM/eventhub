export const MAX_ATTEMPTS = 6; // 1 initial + 5 retries

/**
 * Exponential backoff: delay = 4^(attemptsMade - 1) * 1000ms
 * Sequence: 1s, 4s, 16s, 64s, 256s
 *
 * @param attemptsMade number of attempts already made (1-indexed: 1 after first failure)
 */
export function computeBackoffDelay(attemptsMade: number): number {
  return Math.pow(4, Math.max(0, attemptsMade - 1)) * 1000;
}
