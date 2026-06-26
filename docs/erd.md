# EventHub — Entity Relationship Diagram

> **Graded deliverable** (part of Rubric 2). A starter ERD is seeded below to match the schema described in
> `services/core-api/CLAUDE.md` §F and `system-architecture.md`. Refine columns/types as migrations are written and
> keep this in sync. Renders on GitHub/GitLab and in Mermaid Live.

## ER diagram (Mermaid)

```mermaid
erDiagram
    users ||--o| vendors : "has profile"
    users ||--o| attendees : "has profile"
    vendors ||--o{ events : organizes
    events ||--o{ ticket_types : has
    ticket_types ||--o{ ticket_holds : "reserved by"
    ticket_types ||--o{ tickets : "issued as"
    ticket_types ||--o{ order_items : "purchased in"
    attendees ||--o{ orders : places
    orders ||--o{ order_items : contains
    orders ||--o{ ticket_holds : holds
    orders ||--o| payments : "paid by"
    payments ||--o{ refunds : "refunded by"
    orders ||--o{ tickets : issues
    orders ||--o{ disputes : "may raise"
    vendors ||--o{ payouts : "settled via"
    events ||--o{ waitlist_entries : "waitlisted on"
    attendees ||--o{ waitlist_entries : joins

    users {
        bigint id PK
        string name
        string email UK
        string password
        enum role "admin|vendor|attendee"
        timestamp created_at
        timestamp deleted_at "soft delete"
    }
    vendors {
        bigint id PK
        bigint user_id FK
        string business_name
        enum kyc_status "pending|verified|rejected"
        json payout_account "[PLACEHOLDER] sensitive"
        string webhook_url "nullable"
        decimal commission_rate "nullable override"
        timestamp deleted_at
    }
    attendees {
        bigint id PK
        bigint user_id FK
        string phone "nullable"
        timestamp deleted_at
    }
    events {
        bigint id PK
        bigint vendor_id FK
        string title
        text description
        string timezone "IANA"
        timestamp starts_at "UTC"
        timestamp ends_at "UTC"
        enum status "draft|published|ongoing|completed|cancelled"
        timestamp deleted_at
    }
    ticket_types {
        bigint id PK
        bigint event_id FK
        enum kind "early_bird|vip|general|group_bundle"
        bigint price "minor units"
        string currency "ISO 4217"
        int quantity_total
        int quantity_sold "denormalized counter"
        int group_size "nullable"
        decimal group_discount "nullable"
        timestamp sales_start
        timestamp sales_end
    }
    orders {
        bigint id PK
        bigint attendee_id FK
        enum status "pending|paid|expired|failed|cancelled|refunded"
        bigint total "minor units"
        string currency
        string idempotency_key UK
        timestamp created_at
    }
    order_items {
        bigint id PK
        bigint order_id FK
        bigint ticket_type_id FK
        int quantity
        bigint unit_price "minor units"
    }
    ticket_holds {
        bigint id PK
        bigint order_id FK
        bigint ticket_type_id FK
        int quantity
        enum status "active|released|converted"
        timestamp expires_at "indexed"
    }
    tickets {
        bigint id PK
        bigint order_id FK
        bigint ticket_type_id FK
        string qr_code UK
        timestamp checked_in_at "nullable"
    }
    payments {
        bigint id PK
        bigint order_id FK
        string gateway "stripe_sim|paypal_sim"
        enum status "pending|succeeded|failed"
        string external_ref "[PLACEHOLDER]"
        bigint amount "minor units"
        string currency
    }
    refunds {
        bigint id PK
        bigint payment_id FK
        bigint amount "minor units"
        string policy_applied "100|50|0"
        enum status "pending|completed|failed"
        string reason
    }
    payouts {
        bigint id PK
        bigint vendor_id FK
        bigint gross "minor units"
        bigint commission
        bigint net
        enum status "pending|approved|processing|paid|failed"
        string batch_id
        string idempotency_key UK
    }
    disputes {
        bigint id PK
        bigint order_id FK
        string reason
        enum status "open|resolved|rejected"
        text resolution "nullable"
    }
    waitlist_entries {
        bigint id PK
        bigint event_id FK
        bigint ticket_type_id FK
        bigint attendee_id FK
        int position
        enum status "waiting|offered|claimed|expired"
    }
    ledger_entries {
        bigint id PK
        string subject_type "order|payment|refund|payout"
        bigint subject_id
        string entry_type
        bigint amount "minor units, signed"
        string currency
        timestamp created_at "append-only"
    }
    idempotency_keys {
        bigint id PK
        string key UK
        string request_hash
        json response_payload
        string status
        timestamp created_at
    }
```

## Relationship notes
<!-- FILL: explain each non-obvious relationship and the integrity rules:
- users 1:1 vendors / attendees (role-specific profile).
- ticket_holds vs tickets: holds are transient reservations (15 min); tickets are issued only after payment succeeds.
- orders ↔ payments 1:1 (one charge per order) but payments ↔ refunds 1:N (partial refunds).
- ledger_entries is polymorphic + append-only — the financial source of truth.
- idempotency_keys guards money operations (also in payment-service's own DB).
- Why quantity_sold is denormalized on ticket_types (fast availability check under lock).
-->
