import { api } from "./client";
import type { Vendor } from "./types";

export interface KycDocument {
  type: "trade_license" | "nid" | "bank_statement";
  storage_path: string;
}

export const vendorApi = {
  submitKyc: (documents: KycDocument[]) =>
    api.post<{ vendor: Vendor }>("/vendor/kyc", { documents }),
};
