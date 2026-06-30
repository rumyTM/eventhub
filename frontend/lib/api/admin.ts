import { api } from "./client";
import type { Vendor, VendorListResponse, DisputeItem, DisputeListResponse, Refund } from "./types";

export const adminApi = {
  pendingVendors: (page = 1) =>
    api.get<VendorListResponse>(`/admin/vendors?page=${page}`),

  verifyVendor: (vendorId: string) =>
    api.post<{ vendor: Vendor }>(`/admin/vendors/${vendorId}/verify`),

  rejectVendor: (vendorId: string, reason: string) =>
    api.post<{ vendor: Vendor }>(`/admin/vendors/${vendorId}/reject`, { reason }),

  // Dispute queue (out-of-policy refund contests)
  listDisputes: (page = 1) =>
    api.get<DisputeListResponse>(`/admin/disputes?page=${page}`),

  resolveDispute: (disputeId: string, resolution?: string) =>
    api.post<{ dispute: DisputeItem }>(`/admin/disputes/${disputeId}/resolve`, { resolution }),

  rejectDispute: (disputeId: string, resolution: string) =>
    api.post<{ dispute: DisputeItem }>(`/admin/disputes/${disputeId}/reject`, { resolution }),

  // Legacy: admin-initiated direct refund (event cancellation, etc.)
  initiateRefund: (orderId: string, reason: string) =>
    api.post<{ refund: Refund }>(`/admin/orders/${orderId}/refund`, { reason }),
};
