# EventHub — Multi-Vendor Event Ticketing & Payout Platform

EventHub lets **vendors** create events and sell tickets, **attendees** buy tickets and check in, and **platform
admins** approve vendors, set commission, and resolve disputes. It is built as a small set of services around a
Laravel core, with money handled carefully: every financial operation is **auditable, idempotent, and resilient to
partial failure**.

> **Reviewers start here**, then read [`CLAUDE.md`](./CLAUDE.md) for the full system map. Planning documents are in
> [`docs/`](./docs). The build is tracked in [`PLAN.md`](./PLAN.md) and [`WORKLOG.md`](./WORKLOG.md).
>
> **Video walkthrough:** _[add Loom/Drive/YouTube unlisted link here]_

## Architecture at a glance

| Service | Stack | Port | Responsibility |
|---|---|---|---|
| [`services/core-api`](./services/core-api) | Laravel 11 | 8000 | Events, tickets, orders/holds, vendors, payouts, admin, auth, cron — the orchestrator |
| [`services/payment-service`](./services/payment-service) | Laravel 11 | 8001 | Simulated gateways, charges, refunds, payout execution, idempotency (private) |
| [`services/notification-service`](./services/notification-service) | Node.js + BullMQ | 8002 | Email (simulated), vendor webhooks, retry/backoff, dead-letter, delivery tracking |
| [`frontend`](./frontend) | Next.js 14 | 3000 | Vendor dashboard, attendee pages, admin panel |
| MySQL | 8.0 | 3306 | One database per service |
| Redis | 7 | 6379 | Notification queue + distributed locks |

Communication: frontend → core-api (Sanctum bearer); core-api ↔ payment-service (REST, shared secret +
idempotency key, signed webhook callback); core-api → notification-service (Redis queue); notification-service →
vendors (signed webhooks). Full diagram and contracts in [`CLAUDE.md`](./CLAUDE.md) and
[`docs/system-architecture.md`](./docs/system-architecture.md).

## Run it (docker-compose — recommended)

```bash
cp .env.example .env          # then fill in secrets (placeholders shown)
docker compose up -d --build
docker compose ps             # wait for healthy

# bootstrap data
docker compose exec core-api php artisan migrate --seed
docker compose exec payment-service php artisan migrate --seed
```

Then open: frontend `http://localhost:3000` · core-api `http://localhost:8000/api/v1` ·
payment-service `http://localhost:8001` · notification-service `http://localhost:8002`.

Seed data creates demo `admin`, `vendor`, and `attendee` accounts (credentials printed by the seeder — demo-only,
never real). API documentation (Postman/OpenAPI) is in [`docs/`](./docs) once generated.

### Local / Laragon fallback (no Docker)
> **PHP 8.4+ required for the local path.** Laravel 11's floor is PHP 8.2, but the committed `composer.lock`
> resolves Symfony 8.x components that require `php >= 8.4.1`, so `composer install` against this lock needs
> **PHP 8.4.1 or newer** (8.2/8.3 will fail with a platform error). The Docker image (`php:8.4-cli`) already
> matches this, so the recommended docker-compose path needs no local PHP at all.

Run MySQL + Redis locally and create the three databases (`eventhub_core`, `eventhub_payments`,
`eventhub_notifications`). Then per service:

```bash
# Laravel services (core-api, payment-service)
cd services/core-api && composer install && cp ../../.env.example .env \
  && php artisan key:generate && php artisan migrate --seed && php artisan serve --port=8000
# queue worker + scheduler (separate terminals)
php artisan queue:work    #   php artisan schedule:work

# notification-service
cd services/notification-service && npm install && npm run dev    # :8002

# frontend
cd frontend && npm install && npm run dev                          # :3000
```

## Repository layout

```
.
├── CLAUDE.md                 # system map + onboarding (read this)
├── PLAN.md / WORKLOG.md      # execution checklist + running log
├── docker-compose.yml        # full stack
├── docs/                     # requirement analysis, architecture, ERD, decision log, dev plan
├── .claude/                  # AI workflow: skills, slash commands, review subagents
├── services/
│   ├── core-api/             # Laravel main app  (own CLAUDE.md)
│   ├── payment-service/      # Laravel payment microservice  (own CLAUDE.md)
│   └── notification-service/ # Node notification microservice  (own CLAUDE.md)
└── frontend/                 # Next.js app  (own CLAUDE.md)
```

## AI-augmented workflow
This repo is structured for both human and AI developers. Each service has its own `CLAUDE.md` (productive in
~30 min), plus scoped agent skills and slash commands in `.claude/`. The two Laravel services also use
[Laravel Boost](https://github.com/laravel/boost) (dev-only) to give AI tools version-accurate docs and live
app/DB introspection. See [`CLAUDE.md`](./CLAUDE.md) §7 and the
[AI workflow playbook](./docs/ai-workflow.md) for how the build is driven and how a new developer contributes.

## API documentation

Interactive docs, a Postman collection, and an OpenAPI spec are generated by [Scribe](https://scribe.knuckles.wtf)
from the core-api service's FormRequests and docblock annotations.

| Artifact | Location | Notes |
|---|---|---|
| **HTML docs** (interactive) | `http://localhost:8000/docs` | Served by Laravel once the service is up; includes Try-it-out |
| **Postman collection** | [`docs/postman-collection.json`](./docs/postman-collection.json) | Import into Postman — all endpoints, auth pre-filled |
| **OpenAPI spec** | [`docs/openapi.yaml`](./docs/openapi.yaml) | v3.0.3 — import into Insomnia, Swagger UI, or any OAS tool |

To regenerate (e.g. after adding endpoints):

```bash
cd services/core-api
php artisan scribe:generate
# Updated files: resources/views/scribe/index.blade.php, storage/app/private/scribe/collection.json, storage/app/private/scribe/openapi.yaml
```

### Demo credentials (seeded)

Run `php artisan migrate --seed` in core-api. The seeder creates:

| Role     | Email                    | Password | Notes |
|----------|--------------------------|----------|-------|
| Admin    | admin@eventhub.test      | password | Full admin access (KYC review, payouts, refunds) |
| Vendor   | vendor@eventhub.test     | password | KYC-verified; owns "DhakaTech Summit 2026" + a completed event |
| Attendee | attendee@eventhub.test   | password | Has a paid order (3 × 250 BDT) + pending refund eligibility |

Also seeded: a published event with General / VIP / Early-Bird ticket types, a completed event with a sold ticket
type, a `paid` order with issued tickets, and a `pending` payout (675 BDT net after 10 % commission).

## Testing
Required business-logic tests live in core-api (order processing/holds/oversell, payout calculation, inventory) and
payment-service (idempotency, gateway outcomes). Run `php artisan test` per Laravel service and `npm test` in the
Node service. See each service's `CLAUDE.md`.

## Security
The design deliberately stays **out of PCI-DSS scope** — no raw card data (PAN/CVV) is ever stored or transmitted
(the simulated gateway holds it; we keep only tokens/refs). Separately, as general security + data-privacy: no
secrets/tokens/OTP or KYC/PII in code, logs, tests, or
responses (`[PLACEHOLDER]` everywhere); validated input only; payment/notification endpoints are never public;
financial history is append-only. See [`CLAUDE.md`](./CLAUDE.md) §6.
