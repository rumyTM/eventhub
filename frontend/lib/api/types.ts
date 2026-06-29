// ── Envelope ──────────────────────────────────────────────────────────────────
export interface ApiEnvelope<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, string[]> | null;
}

// ── Shared ─────────────────────────────────────────────────────────────────────
export interface EnumValue {
  value: string;
  label: string;
}

export interface Pagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

// ── Auth ───────────────────────────────────────────────────────────────────────
export interface User {
  id: string;
  name: string;
  email: string;
  role: "admin" | "vendor" | "attendee";
  created_at: string;
}

export interface AuthTokenResponse {
  token: string;
  user: User;
}

// ── Events ─────────────────────────────────────────────────────────────────────
export interface TicketType {
  id: string;
  event_id: string;
  kind: EnumValue;
  price: number;
  currency: string;
  quantity_total: number;
  quantity_sold: number;
  group_size: number | null;
  group_discount: number | null;
  sales_start: string | null;
  sales_end: string | null;
  created_at: string;
  updated_at: string;
}

export interface Event {
  id: string;
  vendor_id: string;
  title: string;
  description: string;
  timezone: string;
  starts_at: string;
  ends_at: string;
  capacity: number;
  status: EnumValue;
  ticket_types?: TicketType[];
  created_at: string;
  updated_at: string;
}

export interface EventListResponse {
  events: Event[];
  pagination: Pagination;
}

// ── Orders ─────────────────────────────────────────────────────────────────────
export interface OrderItem {
  id: string;
  ticket_type_id: string;
  quantity: number;
  unit_price: number;
}

export interface Hold {
  id: string;
  ticket_type_id: string;
  quantity: number;
  status: EnumValue;
  expires_at: string;
}

export interface Order {
  id: string;
  status: EnumValue;
  total: number;
  currency: string;
  commission_rate: string;
  items?: OrderItem[];
  holds?: Hold[];
  hold_expires_at?: string | null;
  created_at: string;
}

export interface OrderListResponse {
  orders: Order[];
  pagination: Pagination;
}

// ── Refund ─────────────────────────────────────────────────────────────────────
export interface Refund {
  id: string;
  amount: number;
  policy_applied: string;
  status: EnumValue;
}

// ── Vendor / KYC ───────────────────────────────────────────────────────────────
export interface Vendor {
  id: string;
  business_name: string;
  legal_name: string;
  trade_license_no: string;
  contact_phone: string;
  address: string;
  kyc_status: EnumValue;
  submitted_at: string | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  created_at: string;
  user?: User;
}

export interface VendorListResponse {
  vendors: Vendor[];
  pagination: Pagination;
}

// ── Payouts ────────────────────────────────────────────────────────────────────
export interface Payout {
  id: string;
  vendor_id: string;
  batch_id: string;
  currency: string;
  gross: number;
  commission: number;
  net: number;
  payable: number;
  reserved_refund: number;
  status: EnumValue;
  created_at: string;
  updated_at: string;
}

export interface PayoutListResponse {
  payouts: Payout[];
  pagination: Pagination;
}

export interface PayoutPreview {
  gross: number;
  commission_rate: number;
  commission: number;
  net: number;
  reserved_refund: number;
  currency: string;
  meets_threshold: boolean;
  threshold: number;
}

// ── Admin ──────────────────────────────────────────────────────────────────────
export interface AdminStats {
  total_events: number;
  total_orders: number;
  total_revenue: number;
  active_vendors: number;
  pending_vendors: number;
  currency: string;
}
