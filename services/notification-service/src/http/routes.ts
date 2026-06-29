import { Router } from 'express';
import type { Request, Response, RequestHandler } from 'express';
import { DeliveryRepository } from '../delivery/DeliveryRepository';
import { getRedis } from '../config/redis';

const deliveryRepo = new DeliveryRepository(getRedis());

function ok(res: Response, data: unknown, message = 'OK'): void {
  res.json({ success: true, message, data });
}

function badRequest(res: Response, message: string, status = 400): void {
  res.status(status).json({ success: false, message, data: null });
}

/** Wraps an async Express handler so it returns void (suppresses no-misused-promises). */
function asyncHandler(
  fn: (req: Request, res: Response) => Promise<void>,
): RequestHandler {
  return (req, res, next) => {
    fn(req, res).catch(next);
  };
}

export function buildRouter(): Router {
  const router = Router();

  router.get('/health', (_req: Request, res: Response) => {
    ok(res, { status: 'healthy', uptime: process.uptime() });
  });

  router.get(
    '/api/v1/deliveries',
    asyncHandler(async (_req, res) => {
      const records = await deliveryRepo.listRecent(50);
      ok(res, { deliveries: records, total: records.length });
    }),
  );

  router.get(
    '/api/v1/deliveries/:idempotencyKey',
    asyncHandler(async (req, res) => {
      const { idempotencyKey } = req.params;
      if (!idempotencyKey) {
        badRequest(res, 'Missing idempotencyKey', 400);
        return;
      }
      const record = await deliveryRepo.getStatus(idempotencyKey);
      if (!record) {
        badRequest(res, 'Delivery record not found', 404);
        return;
      }
      ok(res, { delivery: record });
    }),
  );

  return router;
}
