# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Webhook-as-a-Service (WaaS) MVP — a platform that receives webhooks from third-party services and fans them out to user-defined destination URLs with automatic retries and a delivery dashboard.

**Stack:** Symfony 7 (PHP 8.4) backend + React 18 + Vite frontend, monolith deployment, PostgreSQL 17, Symfony Messenger (Doctrine transport in dev; AWS SQS in production).

## Development Commands

### Full stack (Docker Compose)
```bash
docker compose up              # Start all services (app, frontend, db, worker)
docker compose down
docker compose exec app bash   # Shell into PHP container
```

### Backend (Symfony)
```bash
php bin/console doctrine:migrations:diff     # Generate migration after entity changes
php bin/console doctrine:migrations:migrate  # Apply pending migrations
php bin/console messenger:consume async --time-limit=3600  # Run queue worker
php bin/phpunit                              # Run all unit tests
php bin/phpunit tests/path/to/FooTest.php   # Run a single test file
php bin/phpunit --filter testMethodName      # Run a single test by name
```

### Frontend (run from `frontend/` directory)
```bash
npm run watch  # Vite build --watch → outputs to ../public/build (for use with Symfony dev server)
npm run build  # Production build → output goes to Symfony's public/ directory
```

## Architecture

### Hexagonal Architecture (Ports & Adapters)
The backend is strictly layered:

- **`src/Domain/`** — Pure business entities (`Source`, `Event`, `Endpoint`, `DeliveryAttempt`, `EventEndpointDelivery`, `EventStatus`, `AuditLog`, `User`, `Plan`), value objects, and domain exceptions. No Symfony or Doctrine imports allowed here.
- **`src/Application/`** — Ports (interfaces in `Port/`), use cases (`UseCase/` grouped by entity), Messenger messages (`Message/`), Symfony application events (`Event/`), and value objects (`Value/`). Orchestrates domain objects; no framework code.
- **`src/Entity/`** — Doctrine ORM entities (separate from domain entities). These are infrastructure adapters mapped to the DB tables.
- **`src/Controller/`** — Top-level controllers (`IngestEventController`, `RegistrationController`, `AccountController`, `MeController`, `SpaController`, `HealthCheckController`) plus `Api/v1/` JSON API controllers grouped by resource (`Source/`, `Endpoint/`, `Event/`, `Audit/`, `Dashboard/`, `Log/`).
- **`src/Infrastructure/`** — Concrete adapters: `Http/` (outbound HTTP delivery), `Messaging/` (Messenger handlers), `Persistence/` (Doctrine repositories), `Transaction/` (DB transaction wrapper), `EventDispatcher/` (Symfony event dispatcher adapters), `EventListener/` (Symfony event listeners).
- **`src/Command/`** — Symfony console commands (`PruneRequestUsageCommand`).
- **`src/Security/`** and **`src/EventSubscriber/`** — Symfony security voter/authenticator and event subscribers.

Domain and Application code must never import from `Symfony\` or `Doctrine\` namespaces.

### Queue & Delivery
- Inbound webhook at `POST /in/{uuid}` persists the event and enqueues one Messenger message per active Endpoint — returns `200 OK` immediately.
- The `worker` service processes the queue and POSTs the raw body + headers to each Endpoint URL.
- Adds `X-Webhook-Event-Id: <events.id>` header to all outgoing delivery requests.
- Timeout per attempt: 10 seconds. Success = any `2xx`. 5 attempts max (immediate, 30s, 5m, 30m, 2h).

### Plans & Quota
Each user belongs to a `Plan` (monthly request limit). `IngestEventUseCase` enforces quota before persisting an event: it sums rolling 30-day request usage via `RequestUsageRepositoryPort.sumRolling30Days()` and throws `QuotaExceededException` if the limit is reached. On successful ingest, an `IngestCompletedEvent` is dispatched; `RecordRequestUsageListener` increments today's counter.

Old usage rows are pruned daily by `PruneRequestUsageCommand`, which uses `#[AsCronTask('0 0 * * *')]` (Symfony Scheduler, schedule name `prune-request-usage`) and deletes rows older than 30 days.

### Application Events (Symfony Dispatcher)
`src/Application/Event/` contains two synchronous Symfony events — distinct from Messenger messages:

- `AuditableActionEvent` — dispatched by use cases after any mutating action (create/delete source, endpoint, etc.). `RecordAuditEntryListener` persists an `AuditLog` row.
- `IngestCompletedEvent` — dispatched by `IngestEventUseCase` after a successful ingest. `RecordRequestUsageListener` increments the rolling usage counter.

Dispatching uses `AuditEventDispatcherPort` / `IngestEventDispatcherPort` (ports), implemented by `SymfonyAuditEventDispatcher` / `SymfonyIngestEventDispatcher` in `src/Infrastructure/EventDispatcher/`. This keeps the Application layer free of Symfony imports.

### Event Status Recomputation
`events.status` (`pending` / `delivered` / `failed`) is a denormalized cache derived from `event_endpoint_deliveries`. Rules:
- `failed` — any delivery row is `failed`
- `delivered` — all delivery rows are `delivered`
- `pending` — otherwise

**Critical constraint:** recomputation and the `event_endpoint_deliveries` update must occur atomically in the same DB transaction, querying ALL delivery rows for the event (not inferring from the single row being updated).

### Data Model Key Points
- `sources.inbound_uuid` — UUIDv7, unique, used in `POST /in/{uuid}`
- `event_endpoint_deliveries` — unique on `(event_id, endpoint_id)`; created at enqueue time, updated by worker
- `delivery_attempts` — one row per attempt (up to 5 per event+endpoint pair)
- Required indexes: `events(source_id, received_at DESC)`, `event_endpoint_deliveries(event_id)`, `delivery_attempts(event_id, endpoint_id)`

### Database Migrations
All schema changes via Doctrine Migrations only — no manual SQL. Every migration must be committed alongside the entity change that produced it.

### Frontend
- React Router for routing, `useState` + `fetch` for state (no Redux/Zustand).
- Auth state is managed via `src/contexts/AuthContext.tsx` (React Context + `useAuth()` hook). `ProtectedRoute` uses this context.
- UI layer: **shadcn/ui** components (in `src/components/ui/`) + **Tailwind CSS v4** (Vite plugin, no `tailwind.config.js`). All pages use the shared `<Layout>` component (`src/components/Layout.tsx`).
- In production, the React build is served as static files from Symfony's `public/` directory — no separate Node server.

## Design System

The frontend uses **shadcn/ui** + **Tailwind CSS v4**. All new UI work must follow these rules:

- Use shadcn components from `src/components/ui/` — never write custom component primitives from scratch.
- Style with Tailwind utility classes only — no inline `style={}` objects, no separate CSS files per component.
- Wrap every protected page in `<Layout>` (`src/components/Layout.tsx`).
- Use the `cn()` helper from `src/lib/utils.ts` (clsx + tailwind-merge) when combining conditional classes.

### Component conventions

| Use case | Component |
|---|---|
| Page sections / containers | `Card` + `CardHeader` + `CardContent` |
| Data lists | `Table` + `TableHeader` / `TableBody` / `TableRow` / `TableCell` |
| Status indicators | `Badge` (variant drives color: `default`, `destructive`, `secondary`) |
| Navigation context | `Breadcrumb` |
| Inline errors / API errors | `Alert` |
| Mutation feedback (create, delete) | `Sonner` toast |
| Destructive actions | `Button variant="destructive"` |

### Design tokens

Tokens are CSS variables defined in `frontend/src/index.css` via shadcn's slate base. Customize only through these variables — never hardcode color values.

| Token | Value | Intent |
|---|---|---|
| `--primary` | `oklch(0.585 0.233 264.4)` (indigo) | Brand accent |
| `--destructive` | `oklch(0.577 0.245 27.325)` (red) | Delete / error |
| `--radius` | `0.5rem` | Border radius base |

Dark mode is handled automatically via shadcn's CSS variable blocks — both light and dark themes are defined in `index.css`.

## Testing
- Unit tests only (PHPUnit). Integration tests are out of scope.
- Tests must cover domain logic and use cases in isolation.
- Use mocks/stubs for any port that crosses an infrastructure boundary (HTTP client, DB, queue).
- When a use case or handler gains a `LoggerInterface` constructor argument, pass `new NullLogger()` in tests — no mock expectations needed for logging calls.

## Logging

### Channel
All application logs go through the `hookyard` Monolog channel, keeping them isolated from Symfony framework noise. The channel is declared in `config/packages/monolog.yaml` and creates a `monolog.logger.hookyard` service.

**Do not use `app` as a channel name** — it is Symfony's implicit default and does not create a separate logger service.

### Injecting the logger
Use `#[WithMonologChannel('hookyard')]` at the class level (controllers, use cases, message handlers) alongside a plain `LoggerInterface $logger` constructor parameter. Autowiring resolves the correct channel automatically.

```php
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class MyUseCase
{
    public function __construct(
        // ... other dependencies ...
        private readonly LoggerInterface $logger,
    ) {}
}
```

### Correlation ID
Every HTTP request carries an `X-Request-Id` header managed by `RequestIdSubscriber` (`src/EventSubscriber/`):
- On `kernel.request` (priority 100): reads the header or generates a UUIDv4, stores it on `$request->attributes->set('request_id', $id)`.
- On `kernel.response`: echoes it back as `X-Request-Id`.

Pass `request_id` explicitly through the call chain — controllers read `$request->attributes->get('request_id')` and forward it as the first parameter of every use case `execute()` method. For async messages, `DeliverEventMessage` carries a `requestId` field set at enqueue time so the worker logs with the same ID as the originating request.

### Log levels and required fields
| Situation | Level | Always include |
|---|---|---|
| Normal operation entry / success | `INFO` | `request_id` + relevant entity IDs |
| Domain exception (4xx — not found, ownership violation) | `INFO` | `request_id`, `exception_class` |
| Validation failure | `WARNING` | `request_id`, `violations` |
| Non-2xx HTTP delivery response | `WARNING` | `request_id`, `event_id`, `endpoint_id`, `status_code`, `duration_ms` |
| Transport exception (no response) | `WARNING` | `request_id`, `event_id`, `endpoint_id`, `exception_message` |
| Max delivery attempts exhausted | `ERROR` | `request_id`, `event_id`, `endpoint_id`, `attempt_number` |
| Unhandled exception in message handler | `ERROR` | `request_id`, `exception_class`, `message`, `trace` |
| Internal pipeline steps | `DEBUG` | `request_id` + relevant IDs |

### Structured context — never interpolate
Always pass variable data in the context array (second argument). Never embed values in the message string.

```php
// Correct
$this->logger->info('Ingest complete', ['request_id' => $requestId, 'event_id' => $eventId]);

// Wrong
$this->logger->info("Ingest complete for event {$eventId}");
```

### What not to log
- Do not log full request bodies — use `body_bytes` (byte count) instead.
- Do not log passwords, tokens, or API keys.
