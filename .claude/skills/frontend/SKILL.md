---
name: frontend
description: Work on the EventHub Next.js 14 frontend — vendor dashboard, attendee pages (listing/detail/checkout/orders), and admin panel. Use when the task touches anything under frontend/, or involves React/Next.js views, the API client to core-api, TanStack Query state, shadcn/ui components, checkout flow, or frontend error handling. Scopes you to the web app.
---

# Skill: frontend

**Service boundary:** `frontend/` only (Next.js 14 App Router + TypeScript + shadcn/ui). Consumes core-api
`/api/v1/*`. Graded on working data flow, state management, API integration, and error handling — **not** visual
polish.

## Become productive fast
1. Read `frontend/CLAUDE.md` (views, conventions).
2. Read `../docs/system-architecture.md` API contracts to type the API client and match the
   `{ success, message, data, errors }` envelope.

## Key files & patterns
- Single API layer in `lib/api/`: fetch wrapper that attaches the bearer token, parses the envelope, throws a typed
  `ApiError` (`message` + `errors`) on `success:false`. UI never calls `fetch` directly.
- Route groups by role: `app/(vendor)`, `app/(attendee)`, `app/(admin)`, `app/(auth)`; guard by role, redirect on
  mismatch.
- TanStack Query for server state (keys per resource). Every mutation shows `message` (toast) + maps `errors` to form
  fields. Loading/empty/error states on every data view. Handle 401/403/429.
- Checkout shows a countdown from the hold `expires_at` (15-min hold) and handles expiry.

## Required views
Vendor: create/edit events, ticket types, sales per event, payout history/status, analytics.
Attendee: event listing, event detail + ticket selection, checkout, order history.
Admin: pending vendor approvals, platform metrics, dispute/refund queue.

## How to run / test
```
cd frontend
npm run dev          # http://localhost:3000
npm run lint
npm run build        # must pass before done
npm test             # msw-mocked critical flows
```

## Refuse / fix these
UI calling `fetch` directly instead of the API layer; ignoring `errors` from the envelope; missing loading/error
states; secrets in client code (only `NEXT_PUBLIC_*` config).
