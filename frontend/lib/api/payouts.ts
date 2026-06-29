import { api } from "./client";
import type { Payout, PayoutListResponse, PayoutPreview } from "./types";

export const payoutsApi = {
  // Vendor: own payout history
  myPayouts: (page = 1) =>
    api.get<PayoutListResponse>(`/payouts?page=${page}`),

  preview: (vendorId: string) =>
    api.get<PayoutPreview>(`/vendors/${vendorId}/payouts/preview`),

  // Admin payout management
  list: (page = 1) =>
    api.get<PayoutListResponse>(`/admin/payouts?page=${page}`),

  build: (data: { batch_date?: string }) =>
    api.post<{ payouts_created: number }>("/admin/payouts/build", data),

  execute: (payoutId: string) =>
    api.post<{ payout: Payout }>(`/admin/payouts/${payoutId}/execute`),
};
