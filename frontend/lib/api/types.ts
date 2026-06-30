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
  role: EnumValue;
  vendor?: { kyc_status: EnumValue; submitted_at: string | null } | null;
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
  vendor?: { id: string; business_name: string };
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
  ticket_type?: { id: string; kind: EnumValue } | null;
  quantity: number;
  unit_price: number;
  original_price: number | null;
}

export interface OrderEventSummary {
  id: string;
  title: string;
}

export interface Hold {
  id: string;
  ticket_type_id: string;
  quantity: number;
  status: EnumValue;
  expires_at: string;
}

export interface RefundSummary {
  id: string;
  amount: number;
  policy_applied: string;
  status: EnumValue;
  created_at: string;
}

export interface DisputeSummary {
  id: string;
  status: EnumValue;
  reason: string;
  resolution: string | null;
  created_at: string;
}

export interface TicketSummary {
  id: string;
  qr_code: string;
  ticket_type: { id: string; kind: EnumValue } | null;
  status: EnumValue;
  checked_in_at: string | null;
}

export interface Order {
  id: string;
  status: EnumValue;
  total: number;
  currency: string;
  commission_rate: string;
  items?: OrderItem[];
  events?: OrderEventSummary[];
  attendee?: { id: string; name: string | null };
  holds?: Hold[];
  hold_expires_at?: string | null;
  payment_failed?: boolean;
  has_pending_refund?: boolean;
  latest_refund?: RefundSummary | null;
  latest_dispute?: DisputeSummary | null;
  tickets?: TicketSummary[];
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

export interface DisputeItem {
  id: string;
  order_id: string;
  status: EnumValue;
  reason: string;
  resolution?: string | null;
  order?: {
    id: string;
    total: number;
    currency: string;
    status: EnumValue;
    attendee_name: string | null;
    events: OrderEventSummary[];
    created_at: string;
  };
  created_at: string;
}

export interface DisputeListResponse {
  disputes: DisputeItem[];
  pagination: Pagination;
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
  vendor?: { business_name: string };
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
  commission_rate?: number;
  commission: number;
  net: number;
  payable: number;
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
