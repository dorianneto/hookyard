# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [0.4.0] — 2026-05-10

### Added

#### Backend
- Logs module — `GET /api/v1/logs` endpoint with filterable, paginated access to Monolog `hookyard` channel entries (`ListLogsUseCase`, `DoctrineLogRepository`, `ListLogsController`)
- Audit module — `GET /api/v1/audit` endpoint exposing a trail of user actions (`ListAuditLogsUseCase`, `DoctrineAuditRepository`, `ListAuditLogsController`, `AuditLog` domain entity)
- Automatic audit event recording on key mutations (`AddEndpointUseCase`, `DeleteEndpointUseCase`, `CreateSourceUseCase`, `DeleteSourceUseCase`, `RegisterUserUseCase`, `UpdateAccountUseCase`) via `AuditableActionEvent` and `RecordAuditEntryListener`
- `audit_logs` table and updated index migrations

#### Frontend
- Logs page with filterable, paginated log viewer
- Audit page with filterable audit trail viewer
- Logs and Audit entries added to sidebar navigation
- `Popover` and `Select` shadcn/ui components

### Fixed

- Incorrect database indexes and foreign key constraints corrected across multiple tables

### Changed

- Elastic Beanstalk extension files removed (`.ebextensions/`, `.ebignore`, nginx platform config)
- Legacy `.claude/plans/` planning documents removed

### Documentation

- PRD and tasks files added for logs and audit modules
- Project skills added for PRD generation and changelog updates

---

## [0.3.0] — 2026-05-08

### Changed

#### Backend
- All Doctrine repository queries migrated from DQL to QueryBuilder (`DoctrineDeliveryAttemptRepository`, `DoctrineEndpointRepository`, `DoctrineEventEndpointDeliveryRepository`, `DoctrineEventRepository`, `DoctrinePlanRepository`, `DoctrineRequestUsageRepository`, `DoctrineSourceRepository`, `DoctrineUserRepository`)
- ORM relationship attributes applied directly on all entities (`DeliveryAttempt`, `Endpoint`, `Event`, `EventEndpointDelivery`, `Plan`, `RequestUsage`, `Source`, `User`) replacing legacy XML/YAML mapping

### Fixed

- PHPUnit deprecation notices in use case tests (`AddEndpointUseCase`, `DeleteEndpointUseCase`, `ListEndpointsUseCase`, `EventStatusRecomputation`, `GetEventDetailUseCase`, `ListEventsUseCase`)

---

## [0.2.0] — 2026-05-07

### Added

#### Frontend
- Quick create button in the navbar
- Functional dashboard page with live data
- Sidebar to layout
- Search component in the secondary navigation
- Scroll area component on Source and Event detail pages
- "Get help" link in secondary navigation
- Delete dialog requiring record title confirmation before destructive actions
- Update account page (name, email, password)
- User name field on account settings

#### Backend
- Request quota enforcement per plan on inbound webhook ingestion
- Scheduled command to prune stale `request_usage` rows
- `PATCH /account` endpoint for updating user profile
- User name column on the `users` table
- Foreign key constraints across all relational tables
- Health check endpoint (`GET /health`)
- Cascading deletion of related events when an endpoint is deleted

#### Observability
- Structured logging across all use cases and controllers via the `hookyard` Monolog channel
- `X-Request-Id` subscriber — propagates a correlation ID through every HTTP request and async message
- `request_id` context field on all log entries for CRUD resources and the ingest pipeline
- CloudWatch Logs integration via Elastic Beanstalk extensions

#### Infrastructure
- Supervisord configuration to manage the Messenger worker and scheduler processes inside the container
- Time and memory limits on queue consumer and scheduler commands
- OPcache configured for both dev and prod environments
- nginx as the web server inside the Docker container
- Dedicated Docker Compose service for the Vite `--watch` build

### Fixed

- Layout height not filling available vertical space
- `request_usage.id` serial sequence reset causing primary key conflicts
- Update account route returning incorrect response
- Login and register page styles
- Elastic Beanstalk deployment failing due to `frontend/node_modules` included in the bundle
- npm security vulnerabilities (dependency upgrades)

### Changed

- `DoctrineEventRepository` queries migrated from native SQL to DQL
- Quota counter made synchronous in production to avoid race conditions on plan limits
- Monthly quota values updated across all plans
- CloudWatch log group retention period updated
- NavUser menu items cleaned up (removed unused entries)
- Product name updated in the layout header

### Documentation

- Added ROADMAP file with phased delivery milestones
- Added data model diagram to README
- Added project logo and description to README
- Added architecture metaphor section to README
- Updated CLAUDE.md with logging guidelines and channel conventions

---

## [0.1.0] — 2026-04-09

Initial frontend release — React 18 + Vite frontend with shadcn/ui design system, Tailwind CSS v4, React Router, and all core pages (Sources, Endpoints, Events, delivery detail view).

## [0.0.1] — 2026-04-04

Initial version — Symfony 7 backend with hexagonal architecture, PostgreSQL 17, Symfony Messenger queue, inbound webhook ingestion, fan-out delivery with retries, and basic authentication.
