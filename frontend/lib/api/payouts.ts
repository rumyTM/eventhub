import { api } from "./client";
import type { Payout, PayoutListResponse, PayoutPreview } from "./types";

export const payoutsApi = {
  // Vendor: own payout history + preview + request
  myPayouts: (page = 1) =>
    api.get<PayoutListResponse>(`/payouts?page=${page}`),

  preview: () =>
    api.get<{ preview: PayoutPreview | null }>(`/payouts/preview`),

  requestPayout: () =>
    api.post<{ payout: Payout }>(`/payouts/request`, {}),

  // Admin payout management
  list: (page = 1) =>
    api.get<PayoutListResponse>(`/admin/payouts?page=${page}`),

  build: (data: { batch_date?: string }) =>
    api.post<{ batch_id: string; count: number; payouts: Payout[] }>("/admin/payouts/build", data),

  execute: (payoutId: string) =>
    api.post<{ payout: Payout }>(`/admin/payouts/${payoutId}/execute`),
};
