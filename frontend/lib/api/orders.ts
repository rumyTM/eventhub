import { api } from "./client";
import type { Order, OrderListResponse, Refund } from "./types";

export const ordersApi = {
  list: (page = 1) =>
    api.get<OrderListResponse>(`/orders?page=${page}`),

  show: (id: string) =>
    api.get<{ order: Order }>(`/orders/${id}`),

  checkout: (
    items: { ticket_type_id: string; quantity: number }[],
    idempotencyKey: string,
    gateway?: string,
  ) =>
    api.post<{ order: Order }>(
      "/orders",
      { items, ...(gateway ? { gateway } : {}) },
      { idempotencyKey },
    ),

  refund: (
    orderId: string,
    reason: string,
    items?: { order_item_id: string; quantity: number }[],
  ) =>
    api.post<{ refund: Refund }>(`/orders/${orderId}/refund`, {
      reason,
      ...(items ? { items } : {}),
    }),
};
