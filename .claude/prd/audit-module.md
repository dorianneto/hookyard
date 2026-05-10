# PRD: Audit Module

## Problem

There is currently no way for a user to see a chronological history of changes they have made in the platform — sources created or deleted, endpoints added or removed, account settings updated. Without this trail, debugging configuration mistakes or reviewing past changes requires inspecting raw delivery logs, which do not surface user-initiated mutations at all.

## Goal

Add a dedicated Audit module that records every user-initiated mutating action through a domain event + listener pattern, persists the records in an `audit_logs` table, and exposes them in a filterable, paginated datatable at `/audit`. The audit link lives in the sidebar nav footer (`navSecondary`). Read-only operations and system-initiated background jobs are excluded.

---

## Requirements

### Functional

- **FR-1**: Every mutating use case that has a user actor dispatches an `AuditableActionEvent` domain event after its mutation succeeds.
- **FR-2**: A dedicated Symfony event listener persists the event to the `audit_logs` table without affecting the originating use case (listener failures must be swallowed and logged, not propagated).
- **FR-3**: Audited use cases and their action/resource pairs:
  - `CreateSourceUseCase` → action `create`, resource `source`
  - `DeleteSourceUseCase` → action `delete`, resource `source`
  - `AddEndpointUseCase` → action `create`, resource `endpoint`
  - `DeleteEndpointUseCase` → action `delete`, resource `endpoint`
  - `RegisterUserUseCase` → action `create`, resource `account`
  - `UpdateAccountUseCase` → action `edit`, resource `account`
- **FR-4**: `GET /api/v1/audit` returns a paginated list of audit entries for the authenticated user, filtered by `action[]`, `resource[]`, `date_from`, and `date_to`.
- **FR-5**: The frontend page `/audit` shows a datatable with columns: **When**, **Action**, **Resource**, **Details**.
- **FR-6**: The datatable supports multi-select filtering by action type (`create`, `delete`, `edit`) and date range (from/to date pickers).
- **FR-7**: The Audit link appears in the `navSecondary` section (sidebar footer) and in the `CommandDialog` search palette.

### Non-functional

- **NFR-1**: Audit persistence must not block or roll back the originating use case. The listener must catch all exceptions internally.
- **NFR-2**: `audit_logs` must have indexes on `(user_id, created_at DESC)` and `action` to support the primary query path.
- **NFR-3**: The `metadata` JSONB field must never store passwords, tokens, or raw webhook bodies. For `account.edit`, only store changed field names, not values.
- **NFR-4**: All backend code follows the hexagonal architecture: domain event in `src/Application/Event/`, port in `src/Application/Port/`, listener and dispatcher in `src/Infrastructure/`.

---

## API

### `GET /api/v1/audit`

Authenticated. Returns HTTP 200.

**Query parameters:**

| Param | Type | Required | Notes |
|---|---|---|---|
| `action[]` | string[] | No | One or more of: `create`, `delete`, `edit` |
| `resource[]` | string[] | No | One or more of: `source`, `endpoint`, `account` |
| `date_from` | string | No | ISO 8601, e.g. `2026-04-01T00:00:00Z` |
| `date_to` | string | No | ISO 8601, e.g. `2026-04-30T23:59:59Z` |
| `page` | int | No | Default `1` |
| `per_page` | int | No | Default `20`, max `100` |

**Response 200:**

```json
{
  "data": [
    {
      "id": "0196c5a2-1234-7abc-8def-000000000001",
      "action": "create",
      "resource": "source",
      "resource_id": "0196c5a1-1234-7abc-8def-000000000002",
      "metadata": { "name": "Stripe Webhooks" },
      "created_at": "2026-04-15T10:23:45+00:00"
    }
  ],
  "total": 42,
  "page": 1,
  "per_page": 20
}
```

**Response 400 (invalid date format):**

```json
{ "error": "Invalid date_from format. Expected ISO 8601." }
```

---

## Implementation

### Backend

#### Application Layer — Domain Event

**Create** `src/Application/Event/AuditableActionEvent.php`

```php
final readonly class AuditableActionEvent
{
    public function __construct(
        public string $userId,
        public string $requestId,
        public string $action,      // "create" | "delete" | "edit"
        public string $resource,    // "source" | "endpoint" | "account"
        public ?string $resourceId,
        public array $metadata,
    ) {}
}
```

No Symfony or Doctrine imports. Lives in `App\Application\Event`.

#### Application Layer — Ports

**Create** `src/Application/Port/AuditEventDispatcherPort.php`

```php
interface AuditEventDispatcherPort
{
    public function dispatch(AuditableActionEvent $event): void;
}
```

**Create** `src/Application/Port/AuditRepositoryPort.php`

```php
interface AuditRepositoryPort
{
    /** @return AuditEntry[] */
    public function findByUser(string $userId, AuditFilters $filters): array;
    public function countByUser(string $userId, AuditFilters $filters): int;
    public function save(AuditLog $auditLog): void;  // takes Domain\AuditLog
}
```

`save()` accepts the domain `AuditLog` (from `src/Domain/`) — the listener builds it and calls save; the repository maps it to the Doctrine entity and flushes.

#### Application Layer — Value Objects

**Create** `src/Application/Value/AuditEntry.php`

```php
final readonly class AuditEntry
{
    public function __construct(
        public string $id,
        public string $action,
        public string $resource,
        public ?string $resourceId,
        public array $metadata,
        public \DateTimeImmutable $createdAt,
    ) {}
}
```

**Create** `src/Application/Value/AuditFilters.php`

```php
final readonly class AuditFilters
{
    public function __construct(
        public array $actions = [],           // ["create", "delete", "edit"]
        public array $resources = [],         // ["source", "endpoint", "account"]
        public ?\DateTimeImmutable $dateFrom = null,
        public ?\DateTimeImmutable $dateTo = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
```

**Create** `src/Application/Value/AuditResult.php`

```php
final readonly class AuditResult
{
    public function __construct(
        /** @var AuditEntry[] */
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
```

#### Application Layer — Use Case

**Create** `src/Application/UseCase/Audit/ListAuditLogsUseCase.php`

```php
#[WithMonologChannel('hookyard')]
final class ListAuditLogsUseCase
{
    public function __construct(
        private readonly AuditRepositoryPort $auditRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId, AuditFilters $filters): AuditResult
    {
        $this->logger->info('List audit logs attempt', ['request_id' => $requestId, 'user_id' => $userId]);

        $entries = $this->auditRepository->findByUser($userId, $filters);
        $total   = $this->auditRepository->countByUser($userId, $filters);

        $this->logger->info('List audit logs returned', ['request_id' => $requestId, 'total' => $total]);

        return new AuditResult(entries: $entries, total: $total, page: $filters->page, perPage: $filters->perPage);
    }
}
```

#### Domain Layer — AuditLog

**Create** `src/Domain/AuditLog.php`

`final readonly` class with constructor params: `string $id`, `string $userId`, `string $action`, `string $resource`, `?string $resourceId`, `array $metadata`, `\DateTimeImmutable $createdAt`. No Symfony or Doctrine imports. Getters for all fields. This is the currency crossing the `AuditRepositoryPort::save()` boundary.

#### Infrastructure Layer — Doctrine Entity

**Create** `src/Entity/AuditLog.php`

Doctrine entity mapped to `audit_logs`. Fields: `id` (UUID string, PK), `user` (ManyToOne → `User` entity), `action`, `resource`, `resourceId` (nullable), `metadata` (JSON type), `createdAt`. Add Doctrine `#[ORM\Index]` on `(user_id, created_at)` and `action`.

The `user` field uses a `ManyToOne` relationship — no raw `user_id` VARCHAR column:

```php
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
private User $user;

#[ORM\Column(type: Types::JSON)]
private array $metadata = [];
```

Add a `fromDomain(Domain\AuditLog $log, User $user): self` static factory that maps the domain object to the entity.

#### Infrastructure Layer — Migration

**Create** `migrations/VersionXXXX.php` via:

```bash
php bin/console doctrine:migrations:diff
```

The migration must be committed alongside the entity. Do not write manual SQL.

#### Infrastructure Layer — Repository

**Create** `src/Infrastructure/Persistence/DoctrineAuditRepository.php`

Implements `AuditRepositoryPort`. Uses Doctrine QueryBuilder:

- `findByUser`: applies `WHERE a.user = :userId` (Doctrine resolves the join), optional `AND a.action IN (...)`, optional `AND a.resource IN (...)`, optional `AND a.createdAt >= :dateFrom`, optional `AND a.createdAt <= :dateTo`. Orders by `a.createdAt DESC`. Applies `setFirstResult` / `setMaxResults` for pagination. Maps each `AuditLog` entity to `AuditEntry[]`.
- `countByUser`: same filters, `SELECT COUNT(a.id)`.
- `save`: resolve the `User` entity reference via `$this->entityManager->getReference(User::class, $auditLog->getUserId())`, call `Entity\AuditLog::fromDomain($auditLog, $user)`, then `persist` + `flush`.

#### Infrastructure Layer — Event Dispatcher

**Create** `src/Infrastructure/EventDispatcher/SymfonyAuditEventDispatcher.php`

```php
final class SymfonyAuditEventDispatcher implements AuditEventDispatcherPort
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function dispatch(AuditableActionEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
```

#### Infrastructure Layer — Listener

**Create** `src/Infrastructure/EventListener/RecordAuditEntryListener.php`

```php
#[AsEventListener(event: AuditableActionEvent::class)]
#[WithMonologChannel('hookyard')]
final class RecordAuditEntryListener
{
    public function __construct(
        private readonly AuditRepositoryPort $auditRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AuditableActionEvent $event): void
    {
        try {
            $log = new AuditLog(
                id:         Uuid::v7()->toRfc4122(),
                userId:     $event->userId,
                action:     $event->action,
                resource:   $event->resource,
                resourceId: $event->resourceId,
                metadata:   $event->metadata,
                createdAt:  new \DateTimeImmutable(),
            );

            $this->auditRepository->save($log);

            $this->logger->debug('Audit entry recorded', [
                'request_id' => $event->requestId,
                'action'     => $event->action,
                'resource'   => $event->resource,
                'user_id'    => $event->userId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record audit entry', [
                'request_id'      => $event->requestId,
                'exception_class' => $e::class,
                'message'         => $e->getMessage(),
            ]);
        }
    }
}
```

The listener only depends on `AuditRepositoryPort` and `LoggerInterface` — no Doctrine imports. The `Domain\AuditLog` holds `userId` as a plain string; the repository resolves it to a `User` entity reference before persisting.

#### API Layer — Controller

**Create** `src/Controller/Api/v1/Audit/ListAuditLogsController.php`

```php
#[Route('/api/v1/audit', name: 'audit_list', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListAuditLogsController extends AbstractController
{
    public function __construct(
        private readonly ListAuditLogsUseCase $useCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $userId    = $this->getUser()->getUserIdentifier();

        $dateFrom = null;
        $dateTo   = null;

        if ($raw = $request->query->get('date_from')) {
            $dateFrom = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw);
            if ($dateFrom === false) {
                return $this->json(['error' => 'Invalid date_from format. Expected ISO 8601.'], 400);
            }
        }

        if ($raw = $request->query->get('date_to')) {
            $dateTo = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw);
            if ($dateTo === false) {
                return $this->json(['error' => 'Invalid date_to format. Expected ISO 8601.'], 400);
            }
        }

        $filters = new AuditFilters(
            actions:   $request->query->all('action'),
            resources: $request->query->all('resource'),
            dateFrom:  $dateFrom,
            dateTo:    $dateTo,
            page:      max(1, (int) $request->query->get('page', 1)),
            perPage:   min(100, max(1, (int) $request->query->get('per_page', 20))),
        );

        $result = $this->useCase->execute($requestId, $userId, $filters);

        return $this->json([
            'data'     => array_map(fn (AuditEntry $e) => [
                'id'          => $e->id,
                'action'      => $e->action,
                'resource'    => $e->resource,
                'resource_id' => $e->resourceId,
                'metadata'    => $e->metadata,
                'created_at'  => $e->createdAt->format(\DateTimeInterface::ATOM),
            ], $result->entries),
            'total'    => $result->total,
            'page'     => $result->page,
            'per_page' => $result->perPage,
        ]);
    }
}
```

#### Modifying Existing Use Cases

Inject `AuditEventDispatcherPort $auditDispatcher` as the last constructor parameter in each of the six use cases listed in FR-3. After the primary mutation succeeds, dispatch the event:

```php
$this->auditDispatcher->dispatch(new AuditableActionEvent(
    userId:     $userId,
    requestId:  $requestId,
    action:     'create',
    resource:   'source',
    resourceId: $id,
    metadata:   ['name' => $name],
));
```

Dispatch is placed **after** `$this->sourceRepository->save(...)` and outside any open transaction, so a listener failure cannot roll back the main mutation.

For `DeleteEndpointUseCase`, capture the endpoint URL before the transaction begins (after `findById` succeeds), then dispatch after the transaction commits.

For `UpdateAccountUseCase`, derive `changed_fields` by comparing the incoming `$name` against `$user->getName()` and whether `$newPasswordHash` is non-null:

```php
$changedFields = [];
if ($name !== $user->getName()) $changedFields[] = 'name';
if ($newPasswordHash !== null)  $changedFields[] = 'password';

$this->auditDispatcher->dispatch(new AuditableActionEvent(
    userId:     $userId,
    requestId:  $requestId,
    action:     'edit',
    resource:   'account',
    resourceId: $userId,
    metadata:   ['changed_fields' => $changedFields],
));
```

### Frontend

#### Page Component

**Create** `frontend/src/pages/AuditPage.tsx`

- Protected route, wrapped in `<Layout>`.
- State: `entries[]`, `total`, `page`, `loading`, `error`, `selectedActions: string[]`, `dateFrom: string`, `dateTo: string`.
- Fetches `GET /api/v1/audit` on mount and whenever filters or page change.
- Filter bar (above the table): multi-select for action (`create`, `delete`, `edit`) using shadcn `Select` + `Badge` chips; two `<input type="date">` fields for date range.
- Table columns: **When** (`created_at` formatted as `DD MMM YYYY HH:mm`), **Action** (`Badge` variant: `create` → `default`, `delete` → `destructive`, `edit` → `secondary`), **Resource** (capitalised resource string), **Details** (comma-separated `key=value` from `metadata`, omit sensitive values).
- Pagination: previous/next `Button` pair + `"Page X of Y"` label.
- Empty state: `<TableRow>` spanning all columns with `"No audit records found."`.
- Loading: skeleton rows using shadcn `Skeleton`.
- API errors: `Alert` with `variant="destructive"`.

#### Route

**Modify** `frontend/src/App.tsx`

Add inside the protected routes:

```tsx
<Route path="/audit" element={<AuditPage />} />
```

#### Sidebar Navigation

**Modify** `frontend/src/components/AppSidebar.tsx`

Add to `data.navSecondary` (before "Get Help"):

```tsx
import { IconClipboardList } from "@tabler/icons-react";

{
  title: "Audit",
  url: "/audit",
  icon: IconClipboardList,
}
```

#### Command Palette

**Modify** `frontend/src/components/NavSecondary.tsx`

Add a `CommandItem` inside the `CommandGroup` for the Audit module:

```tsx
<CommandItem onSelect={() => { setOpen(false); navigate("/audit"); }}>
  Audit
</CommandItem>
```

---

## Files Summary

| Action | File |
|---|---|
| Create | `src/Domain/AuditLog.php` |
| Create | `src/Application/Event/AuditableActionEvent.php` |
| Create | `src/Application/Port/AuditEventDispatcherPort.php` |
| Create | `src/Application/Port/AuditRepositoryPort.php` |
| Create | `src/Application/Value/AuditEntry.php` |
| Create | `src/Application/Value/AuditFilters.php` |
| Create | `src/Application/Value/AuditResult.php` |
| Create | `src/Application/UseCase/Audit/ListAuditLogsUseCase.php` |
| Create | `src/Entity/AuditLog.php` |
| Create | `migrations/VersionXXXX.php` (generated) |
| Create | `src/Infrastructure/Persistence/DoctrineAuditRepository.php` |
| Create | `src/Infrastructure/EventDispatcher/SymfonyAuditEventDispatcher.php` |
| Create | `src/Infrastructure/EventListener/RecordAuditEntryListener.php` |
| Create | `src/Controller/Api/v1/Audit/ListAuditLogsController.php` |
| Modify | `src/Application/UseCase/Source/CreateSourceUseCase.php` |
| Modify | `src/Application/UseCase/Source/DeleteSourceUseCase.php` |
| Modify | `src/Application/UseCase/Endpoint/AddEndpointUseCase.php` |
| Modify | `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php` |
| Modify | `src/Application/UseCase/RegisterUserUseCase.php` |
| Modify | `src/Application/UseCase/UpdateAccountUseCase.php` |
| Create | `frontend/src/pages/AuditPage.tsx` |
| Modify | `frontend/src/App.tsx` |
| Modify | `frontend/src/components/AppSidebar.tsx` |
| Modify | `frontend/src/components/NavSecondary.tsx` |

---

## Verification

| # | Check |
|---|---|
| 1 | `GET /api/v1/audit` returns HTTP 401 with no auth token |
| 2 | `GET /api/v1/audit` returns HTTP 200 with `{ data, total, page, per_page }` when authenticated |
| 3 | Creating a source via the UI produces an audit entry with `action=create`, `resource=source` |
| 4 | Deleting a source produces an entry with `action=delete`, `resource=source` |
| 5 | Adding an endpoint produces an entry with `action=create`, `resource=endpoint` |
| 6 | Deleting an endpoint produces an entry with `action=delete`, `resource=endpoint` |
| 7 | Updating account name produces an entry with `action=edit`, `resource=account`, `metadata.changed_fields=["name"]` |
| 8 | Registering a new account produces an entry with `action=create`, `resource=account` |
| 9 | `GET /api/v1/audit?action[]=create` returns only `create` entries |
| 10 | `GET /api/v1/audit?date_from=invalid` returns HTTP 400 with error message |
| 11 | `GET /api/v1/audit?per_page=200` clamps to 100 |
| 12 | Pagination: `total` matches row count, `page=2` returns the next 20 records |
| 13 | `/audit` route renders `AuditPage` and shows the datatable |
| 14 | "Audit" link appears in the sidebar nav footer |
| 15 | "Audit" entry appears in the `⌘K` command palette and navigates to `/audit` |
| 16 | `delete` action badge renders with `destructive` variant (red) |
| 17 | `php bin/phpunit` passes with no failures after all changes |
| 18 | A listener exception does not cause the originating use case to throw |
| 19 | `audit_logs` table exists after running migrations with `user_id` (FK), `action`, `resource`, `created_at` columns |
| 20 | `metadata` for `account.edit` contains `changed_fields` array, never a raw password value |
