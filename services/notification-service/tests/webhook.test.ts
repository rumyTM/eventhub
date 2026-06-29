import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import nock from 'nock';
import { createHmac } from 'crypto';
import { VendorWebhookChannel } from '../src/channels/VendorWebhookChannel';
import { signPayload } from '../src/lib/hmac';
import type { WebhookJobPayload } from '../src/jobs/types';

const TEST_SECRET = 'test-webhook-secret-not-real';
const VENDOR_URL = 'https://vendor.example.com';
const WEBHOOK_PATH = '/webhook';

function makePayload(): WebhookJobPayload {
  return {
    type: 'order.created',
    idempotencyKey: 'wh-idem-1',
    trace_id: 'trace-123',
    recipient: { vendorId: 'vendor-01', email: 'vendor@example.com' },
    url: `${VENDOR_URL}${WEBHOOK_PATH}`,
    data: { orderId: 'order-01', amount: 5000, currency: 'BDT' },
  };
}

describe('signPayload (HMAC util)', () => {
  it('produces correct HMAC-SHA256 hex digest', () => {
    const body = '{"type":"order.created"}';
    const expected = createHmac('sha256', TEST_SECRET).update(body, 'utf8').digest('hex');
    expect(signPayload(body, TEST_SECRET)).toBe(expected);
  });

  it('produces different signatures for different secrets', () => {
    const body = '{"test":true}';
    expect(signPayload(body, 'secret-a')).not.toBe(signPayload(body, 'secret-b'));
  });
});

describe('VendorWebhookChannel', () => {
  beforeEach(() => {
    nock.cleanAll();
    nock.disableNetConnect();
  });

  afterEach(() => {
    nock.cleanAll();
    nock.enableNetConnect();
  });

  it('signs the request body and sets X-EventHub-Signature header', async () => {
    const payload = makePayload();
    const channel = new VendorWebhookChannel(TEST_SECRET);

    let capturedSignature: string | undefined;

    nock(VENDOR_URL)
      .post(WEBHOOK_PATH)
      .reply(function (_uri, _requestBody) {
        capturedSignature = this.req.headers['x-eventhub-signature'] as string;
        const expectedBody = JSON.stringify({
          type: payload.type,
          idempotencyKey: payload.idempotencyKey,
          data: payload.data,
        });
        const expected = signPayload(expectedBody, TEST_SECRET);
        expect(capturedSignature).toBe(expected);
        return [200, { ok: true }];
      });

    await channel.send(payload);
    expect(capturedSignature).toBeTruthy();
  });

  it('sets Log-Trace-ID header for tracing correlation', async () => {
    const payload = makePayload();
    const channel = new VendorWebhookChannel(TEST_SECRET);
    let capturedTraceId: string | undefined;

    nock(VENDOR_URL)
      .post(WEBHOOK_PATH)
      .reply(function () {
        capturedTraceId = this.req.headers['log-trace-id'] as string;
        return [200, { ok: true }];
      });

    await channel.send(payload);
    expect(capturedTraceId).toBe(payload.trace_id);
  });

  it('throws (triggering BullMQ retry) on 500 response', async () => {
    const channel = new VendorWebhookChannel(TEST_SECRET);
    nock(VENDOR_URL).post(WEBHOOK_PATH).reply(500, 'Internal Server Error');

    await expect(channel.send(makePayload())).rejects.toThrow();
  });

  it('throws on 4xx response', async () => {
    const channel = new VendorWebhookChannel(TEST_SECRET);
    nock(VENDOR_URL).post(WEBHOOK_PATH).reply(404, 'Not Found');

    await expect(channel.send(makePayload())).rejects.toThrow();
  });

  it('throws on network error (timeout / connection refused)', async () => {
    const channel = new VendorWebhookChannel(TEST_SECRET);
    nock(VENDOR_URL).post(WEBHOOK_PATH).replyWithError('ECONNREFUSED');

    await expect(channel.send(makePayload())).rejects.toThrow();
  });

  it('succeeds on 2xx response', async () => {
    const channel = new VendorWebhookChannel(TEST_SECRET);
    nock(VENDOR_URL).post(WEBHOOK_PATH).reply(200, { ok: true });

    const result = await channel.send(makePayload());
    expect(result.success).toBe(true);
  });

  it('throws when url is missing from payload', async () => {
    const channel = new VendorWebhookChannel(TEST_SECRET);
    const payload = { ...makePayload(), url: '' };

    await expect(channel.send(payload)).rejects.toThrow(/missing url/i);
  });
});
