# frontend — EventHub Web App (Next.js 14)

> Functional UI demonstrating the data flow between frontend and core-api. **Not** graded on pixel-perfect design —
> graded on whether the data flow, state management, API integration, and error handling are real and working. Root
> context: [`../CLAUDE.md`](../CLAUDE.md).

---

## A. Stack
- **Next.js 14 (App Router) + TypeScript**, **shadcn/ui** (Tailwind) component library.
- Server state via **TanStack Query** (React Query); auth token kept in an httpOnly cookie or memory + context.
- One typed **API client** wrapping core-api `/api/v1/*`; it understands the `{ success, message, data, errors }`
  envelope and surfaces `errors` for form-field display and `message` for toasts.

## B. Required views (the grading checklist)
**Vendor dashboard** — create/edit events, configure ticket types, view sales per event, payout history + status,
basic analytics (tickets sold, revenue).
**Attendee pages** — event listing/discovery, event detail with ticket-type selection, checkout flow (hold → pay →
confirmation), order history.
**Admin panel** — pending vendor approvals, platform metrics overview, dispute/refund queue.

Route groups: `app/(vendor)`, `app/(attendee)`, `app/(admin)`, `app/(auth)`. Guard each by role from the auth token;
redirect on mismatch.

## C. Conventions
- **API layer in one place** (`lib/api/`): a fetch wrapper that attaches the bearer token, parses the envelope, throws
  a typed `ApiError` (carrying `message` + `errors`) on `success:false`. UI never calls `fetch` directly.
- **State:** React Query for server data (keys per resource), React state/context for local UI. No global store unless
  needed.
- **Error handling:** every mutation surfaces `message` (toast) and maps `errors` to the relevant form fields.
  Loading and empty states on every data view. Handle 401 (→ login), 403 (→ forbidden), 429 (→ retry-after notice).
- **Checkout** reflects the 15-minute hold: show a countdown from the hold's `expires_at`; handle expiry gracefully.
- **Types** generated from / mirroring the API contracts in `docs/system-architecture.md`.
- Env: `NEXT_PUBLIC_API_BASE_URL` → core-api. No secrets in client code.

## D. Testing
Component/integration tests for the critical flows (checkout, event create, vendor approval) with the API client
mocked (msw). Keep it meaningful, not exhaustive — the backend holds the required business-logic tests.

## E. Definition of done
All three role areas render and talk to core-api · envelope parsed + errors shown · loading/empty/error states ·
`npm run lint` + `npm run build` clean · `WORKLOG.md` updated.
