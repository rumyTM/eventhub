import { createHmac, timingSafeEqual } from 'crypto';

export function signPayload(body: string, secret: string): string {
  return createHmac('sha256', secret).update(body, 'utf8').digest('hex');
}

export function verifySignature(body: string, signature: string, secret: string): boolean {
  const expected = signPayload(body, secret);
  try {
    return timingSafeEqual(Buffer.from(signature, 'hex'), Buffer.from(expected, 'hex'));
  } catch {
    return false;
  }
}
