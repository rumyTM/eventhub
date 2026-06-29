import { api } from "./client";
import type { Vendor, VendorListResponse, Order, OrderListResponse, Refund } from "./types";

export const adminApi = {
  pendingVendors: (page = 1) =>
    api.get<VendorListResponse>(`/admin/vendors?page=${page}`),

  verifyVendor: (vendorId: string) =>
    api.post<{ vendor: Vendor }>(`/admin/vendors/${vendorId}/verify`),

  rejectVendor: (vendorId: string, reason: string) =>
    api.post<{ vendor: Vendor }>(`/admin/vendors/${vendorId}/reject`, { reason }),

  // Dispute/refund queue — uses the orders listing filtered by status
  disputedOrders: (page = 1) =>
    api.get<OrderListResponse>(`/orders?status=disputed&page=${page}`),

  initiateRefund: (
    orderId: string,
    reason: string,
  ) =>
    api.post<{ refund: Refund }>(`/admin/orders/${orderId}/refund`, { reason }),
};
