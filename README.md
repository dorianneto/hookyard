<p align="center"><a href="https://hookyard.dorianneto.com" target="_blank"><img src="https://raw.githubusercontent.com/dorianneto/hookyard-art/refs/heads/main/h-white-logo.svg" width="400" alt="HookYard Logo"></a></p>

# About HookYard

Open Source **Webhook-as-a-Service (WaaS)** platform that receives webhooks from third-party services and fans them out to user-defined destination URLs with automatic retries and a delivery dashboard.

<details>
<summary>The metaphor</summary>

> _A rail yard is a place where trains (incoming webhooks) arrive, get sorted by destination, and dispatched out on different tracks (endpoints). Cars can be re-routed if a track fails — just like retries. The yard master (the worker) orchestrates everything without the sender needing to know the details._

| Rail yard concept | WaaS concept |
|-------------------|--------------|
| Incoming train | Inbound webhook (POST /in/{uuid}) |
| Rail yard | HookYard platform |
| Track / destination | Endpoint URL |
| Dispatch | Fan-out to endpoints |
| Re-routing on failure | Automatic retry (5 attempts) |
| Yard master | Queue worker |
| Cargo manifest | Delivery dashboard |

</details>

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4 / Symfony 7 |
| Frontend | React 18 + TypeScript + Vite + shadcn/ui + Tailwind CSS v4 |
| Database | PostgreSQL 17 |
| Queue | Symfony Messenger (AWS SQS) |
| Deployment | Docker (nginx + PHP-FPM + supervisord) |

## Architecture

The backend follows **Hexagonal Architecture** (Ports & Adapters), keeping business logic completely isolated from the framework.

<details>
<summary>See diagram</summary>

```mermaid
graph TD
    subgraph Driving["Driving Adapters (input)"]
        HTTP["HTTP Controllers\nsrc/Controller/"]
        Worker["Queue Worker\n(Messenger Handlers)"]
    end

    subgraph Application["Application Layer\nsrc/Application/"]
        UC["Use Cases\nUseCase/"]
        Ports["Ports (interfaces)\nPort/"]
    end

    subgraph Domain["Domain Layer\nsrc/Domain/"]
        Entities["Entities & Value Objects\nUser, Source, Endpoint\nEvent, DeliveryAttempt"]
        Rules["Business Rules\n& Exceptions"]
    end

    subgraph Driven["Driven Adapters (output)"]
        DB["Doctrine Repositories\nsrc/Infrastructure/Persistence/"]
        Security["Security Handlers\nsrc/Security/"]
        Ext["External HTTP\n(outbound deliveries)"]
    end

    HTTP --> UC
    Worker --> UC
    UC --> Ports
    UC --> Entities
    Ports -.->|implemented by| DB
    Ports -.->|implemented by| Ext
    DB --> Security

    style Domain fill:#1a3a2a,color:#fff,stroke:#2d6a4f
    style Application fill:#1a2a3a,color:#fff,stroke:#2d4f6a
    style Driving fill:#2a1a1a,color:#fff,stroke:#6a2d2d
    style Driven fill:#2a2a1a,color:#fff,stroke:#6a5d2d
```
</details>

### Layer Rules

- **Domain** (`src/Domain/`) — pure PHP: entities, value objects, domain exceptions. Zero imports from `Symfony\` or `Doctrine\`.
- **Application** (`src/Application/`) — use cases that orchestrate domain objects through port interfaces.
- **Infrastructure** (`src/Infrastructure/`, `src/Controller/`, `src/Security/`) — Symfony/Doctrine adapters that implement the ports and expose HTTP endpoints.

### Webhook Delivery Flow

```
POST /in/{uuid}
    → persist Event
    → enqueue one Messenger message per active Endpoint
    → return 200 OK immediately

Worker:
    → POST raw body + headers to Endpoint URL
    → adds X-Webhook-Event-Id header
    → 5 attempts: immediate → 30s → 5m → 30m → 2h
    → recomputes events.status atomically (pending / delivered / failed)
```

## Development

### Start all services

```bash
docker compose up -d
```

### Backend (inside container or host)

```bash
php bin/console doctrine:migrations:diff      # generate migration from entity changes
php bin/console doctrine:migrations:migrate   # apply pending migrations
php bin/phpunit                               # run all tests
```

### Frontend

```bash
npm run build  # production build → public/
npm run watch  # Vite dev watch
```

UI is built with **shadcn/ui** components and **Tailwind CSS v4**. All components live in `frontend/src/components/ui/`.

## Data Model

<details>
<summary>See diagram</summary>


```mermaid
erDiagram
    users {
        int id PK
        string email
        string password_hash
        string name
        int plan_id FK
        timestamp created_at
    }

    plans {
        int id PK
        string name
        int monthly_request_limit
        timestamp created_at
    }

    request_usage {
        int id PK
        int user_id FK
        date bucket_date
        int count
    }

    sources {
        int id PK
        int user_id FK
        string name
        uuid inbound_uuid
        timestamp created_at
    }

    endpoints {
        int id PK
        int source_id FK
        string url
        timestamp created_at
    }

    events {
        int id PK
        int source_id FK
        string method
        json headers
        text body
        enum status
        timestamp received_at
    }

    event_endpoint_deliveries {
        int id PK
        int event_id FK
        int endpoint_id FK
        enum status
        timestamp created_at
        timestamp updated_at
    }

    delivery_attempts {
        int id PK
        int event_id FK
        int endpoint_id FK
        smallint attempt_number
        smallint status_code
        text response_body
        int duration_ms
        timestamp attempted_at
    }

    audit_logs {
        string id PK
        int user_id FK
        string action
        string resource
        string resource_id
        json metadata
        timestamp created_at
    }

    users }o--|| plans : "belongs to"
    users ||--o{ request_usage : tracks
    users ||--o{ sources : owns
    users ||--o{ audit_logs : generates
    sources ||--o{ endpoints : has
    sources ||--o{ events : receives
    events ||--o{ event_endpoint_deliveries : tracks
    endpoints ||--o{ event_endpoint_deliveries : tracks
    event_endpoint_deliveries ||--o{ delivery_attempts : logs
```
</details>

### Key relationships

- A **User** belongs to a **Plan** that defines a `monthly_request_limit`. Rolling 30-day usage is tracked per-day in `request_usage`; ingest is rejected once the limit is reached.
- A **Source** belongs to one user and has many **Endpoints** and many **Events**.
- When an Event arrives, one **`event_endpoint_deliveries`** row is created per active Endpoint (unique on `(event_id, endpoint_id)`).
- Each delivery row accumulates up to 5 **`delivery_attempts`** (exponential backoff: immediate → 30s → 5m → 30m → 2h).
- `events.status` (`pending` / `delivered` / `failed`) is a denormalized cache recomputed atomically from all delivery rows every time a delivery row changes.
- **`audit_logs`** records every mutating user action (source/endpoint create & delete, account updates, registration) with structured metadata.
