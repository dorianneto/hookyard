# PRD: Logs Page

## Problem

Users have no cross-cutting view of delivery activity. To inspect delivery outcomes today they must navigate source-by-source and then event-by-event. There is no single place to see all webhook deliveries in one list, filter by endpoint or source, and spot patterns across the entire account.

## Goal

Add a `/logs` route that lists all `event_endpoint_deliveries` for the authenticated user in a sortable data table with server-side filtering by endpoint(s) and source(s), plus pagination. Back it with a new `GET /api/v1/logs` endpoint.

---

## Requirements

### Functional

- **FR-1**: A new `GET /api/v1/logs` endpoint returns a paginated list of delivery log entries for the authenticated user, ordered by `event.received_at DESC`.
- **FR-2**: Each log entry includes: event ID, event received timestamp, source name, source ID, endpoint ID, endpoint URL, delivery status, attempt count, latest attempt timestamp, and latest attempt HTTP status code.
- **FR-3**: The endpoint accepts optional `endpoint_ids[]` query parameters to filter by one or more specific endpoint IDs.
- **FR-4**: The endpoint accepts optional `source_ids[]` query parameters to filter by one or more specific source IDs (interpreting "filter by events" as filtering by the source that produced them).
- **FR-5**: The endpoint accepts an optional `status` query parameter (`pending` | `delivered` | `failed`) to filter by delivery status.
- **FR-6**: The endpoint accepts `page` (default `1`) and `per_page` (default `20`, max `100`) parameters for pagination.
- **FR-7**: The response wraps results in `{ data, total, page, perPage }`.
- **FR-8**: All results are scoped to the authenticated user — no cross-user data exposure.
- **FR-9**: A new `/logs` route renders the logs page inside the shared `<Layout>`.
- **FR-10**: The logs page shows a data table with columns: Received At, Source, Endpoint URL, Status (badge), Attempts, Last Attempt At, Last HTTP Code.
- **FR-11**: Above the table, two multi-select filter controls allow filtering by endpoint(s) and by source(s). Filters can be applied individually or in combination.
- **FR-12**: A status dropdown filter lets the user filter by delivery status (All / Pending / Delivered / Failed).
- **FR-13**: A "Clear filters" button appears when any filter is active and resets all filters.
- **FR-14**: The table has pagination controls (Previous / Next buttons, current page indicator, total count).
- **FR-15**: Sidebar navigation gains a "Logs" item linking to `/logs`.

### Non-functional

- **NFR-1**: All filtering and pagination is server-side — no client-side array slicing.
- **NFR-2**: One SQL round-trip for data rows; a separate count query fires only for pagination totals.
- **NFR-3**: Architecture constraints preserved: Domain layer — zero Symfony/Doctrine imports; Application — ports only; Infrastructure — implementations.
- **NFR-4**: Data table built with `@tanstack/react-table` following the shadcn Data Table recipe.
- **NFR-5**: Filter dropdowns load from existing APIs (`GET /api/v1/sources`, `GET /api/v1/sources/{id}/endpoints`) — no new list endpoints required.

---

## API

### `GET /api/v1/logs`

Authenticated. Returns HTTP 200.

**Query parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `endpoint_ids[]` | string[] | — | Filter by endpoint ID(s) |
| `source_ids[]` | string[] | — | Filter by source ID(s) |
| `status` | `pending` \| `delivered` \| `failed` | — | Filter by delivery status |
| `page` | int | `1` | Page number |
| `per_page` | int | `20` | Rows per page (max `100`) |

**Response:**

```json
{
  "data": [
    {
      "eventId": "01960000-0000-7000-0000-000000000000",
      "eventReceivedAt": "2026-05-02T14:35:22+00:00",
      "sourceName": "My Stripe",
      "sourceId": "...",
      "endpointId": "...",
      "endpointUrl": "https://example.com/webhook",
      "deliveryStatus": "delivered",
      "attemptCount": 1,
      "latestAttemptAt": "2026-05-02T14:35:23+00:00",
      "latestAttemptStatusCode": 200
    }
  ],
  "total": 150,
  "page": 1,
  "perPage": 20
}
```

`latestAttemptAt` and `latestAttemptStatusCode` are `null` when no delivery attempt has been made yet.

---

## Implementation

### Backend

#### Value Objects

**Create** `src/Application/Value/LogEntry.php`

```php
final readonly class LogEntry
{
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $eventReceivedAt,
        public string $sourceName,
        public string $sourceId,
        public string $endpointId,
        public string $endpointUrl,
        public string $deliveryStatus,
        public int $attemptCount,
        public ?\DateTimeImmutable $latestAttemptAt,
        public ?int $latestAttemptStatusCode,
    ) {}
}
```

**Create** `src/Application/Value/LogFilters.php`

```php
final readonly class LogFilters
{
    public function __construct(
        public array $endpointIds = [],
        public array $sourceIds = [],
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
```

**Create** `src/Application/Value/LogsResult.php`

```php
final readonly class LogsResult
{
    /** @param LogEntry[] $entries */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
```

#### Port

**Create** `src/Application/Port/LogRepositoryPort.php`

```php
interface LogRepositoryPort
{
    /** @return LogEntry[] */
    public function findByUser(string $userId, LogFilters $filters): array;
    public function countByUser(string $userId, LogFilters $filters): int;
}
```

#### Repository

**Create** `src/Infrastructure/Persistence/DoctrineLogRepository.php`

Inject `Doctrine\DBAL\Connection`. Implements `LogRepositoryPort`.

**Data query** (parameterized; dynamic `WHERE` clauses appended only when filter arrays are non-empty):

```sql
SELECT
    e.id              AS event_id,
    e.received_at     AS event_received_at,
    s.name            AS source_name,
    s.id              AS source_id,
    ep.id             AS endpoint_id,
    ep.url            AS endpoint_url,
    eed.status        AS delivery_status,
    COALESCE(da_agg.attempt_count, 0) AS attempt_count,
    da_agg.latest_attempt_at,
    da_last.status_code AS latest_attempt_status_code
FROM event_endpoint_deliveries eed
JOIN events    e  ON e.id  = eed.event_id
JOIN sources   s  ON s.id  = e.source_id
JOIN endpoints ep ON ep.id = eed.endpoint_id
LEFT JOIN (
    SELECT event_id, endpoint_id,
           COUNT(*)          AS attempt_count,
           MAX(attempted_at) AS latest_attempt_at
    FROM delivery_attempts
    GROUP BY event_id, endpoint_id
) da_agg ON da_agg.event_id   = eed.event_id
        AND da_agg.endpoint_id = eed.endpoint_id
LEFT JOIN LATERAL (
    SELECT status_code
    FROM delivery_attempts
    WHERE event_id    = eed.event_id
      AND endpoint_id = eed.endpoint_id
    ORDER BY attempt_number DESC
    LIMIT 1
) da_last ON TRUE
WHERE s.user_id = :userId
  -- AND ep.id = ANY(:endpointIds)   appended when endpointIds non-empty
  -- AND s.id  = ANY(:sourceIds)     appended when sourceIds non-empty
  -- AND eed.status = :status        appended when status non-null
ORDER BY e.received_at DESC
LIMIT :limit OFFSET :offset
```

The count query reuses the same `FROM` / `JOIN` / `WHERE` block with `SELECT COUNT(*) AS total` and no `LIMIT`/`OFFSET`.

Map each result row to a `LogEntry`, parsing `event_received_at` and `latest_attempt_at` as `\DateTimeImmutable` (or `null`).

#### Use Case

**Create** `src/Application/UseCase/Log/ListLogsUseCase.php`

- `#[WithMonologChannel('hookyard')]`
- Constructor: `LogRepositoryPort $logRepository`, `LoggerInterface $logger`
- `execute(string $requestId, string $userId, LogFilters $filters): LogsResult`
- Log `INFO` on entry (`request_id`) and on success (`request_id`, `total`)

#### Controller

**Create** `src/Controller/Api/v1/Log/ListLogsController.php`

- `#[Route('/logs', name: 'list_logs', methods: ['GET'])]`
- `#[WithMonologChannel('hookyard')]`
- Constructor: `ListLogsUseCase`, `Security`, `LoggerInterface`
- Parse `endpoint_ids[]`, `source_ids[]`, `status`, `page`, `per_page` from query string
- Clamp `per_page` to max 100
- Return HTTP 401 if not authenticated
- Build `LogFilters`, call use case, serialize `LogsResult` to the JSON shape above
- Log `INFO` on request received (`request_id`)

#### Unit Tests

**Create** `tests/Unit/Application/UseCase/Log/ListLogsUseCaseTest.php`

| Test | What it verifies |
|---|---|
| `testExecuteReturnsLogsResult` | Repository called with correct `userId` and `filters`; `LogsResult` propagated unchanged |
| `testExecuteWithNoFilters` | Empty `LogFilters` passes through; empty entries array returned without error |
| `testExecuteWithEndpointFilter` | `filters->endpointIds` forwarded to repository |
| `testExecuteWithStatusFilter` | `filters->status` forwarded to repository |

Use `createMock(LogRepositoryPort::class)` and `new NullLogger()`.

---

### Frontend

#### Install dependency

```bash
# run inside frontend/
npm install @tanstack/react-table
```

#### TypeScript interfaces (inline in `LogsPage.tsx`)

```typescript
interface LogEntry {
  eventId: string
  eventReceivedAt: string
  sourceName: string
  sourceId: string
  endpointId: string
  endpointUrl: string
  deliveryStatus: 'pending' | 'delivered' | 'failed'
  attemptCount: number
  latestAttemptAt: string | null
  latestAttemptStatusCode: number | null
}

interface LogsResponse {
  data: LogEntry[]
  total: number
  page: number
  perPage: number
}
```

#### New `LogsPage.tsx`

**Create** `frontend/src/pages/LogsPage.tsx`

State: `logs: LogEntry[]`, `loading: boolean`, `error: string | null`, `total: number`, `page: number`, `selectedEndpointIds: string[]`, `selectedSourceIds: string[]`, `selectedStatus: string`, `sources` (for filter dropdown), `endpointOptions` (for filter dropdown).

On mount: fetch `GET /api/v1/sources` to populate the source filter list. Endpoint options are populated from all sources (fetch `GET /api/v1/sources/{id}/endpoints` per source as needed).

Data fetch (called on mount and whenever filters or page change):
```
GET /api/v1/logs?page=N&per_page=20[&source_ids[]=...][&endpoint_ids[]=...][&status=...]
```

**Table columns:**

| Column | Accessor | Notes |
|---|---|---|
| Received At | `eventReceivedAt` | `new Date(value).toLocaleString()` |
| Source | `sourceName` | |
| Endpoint URL | `endpointUrl` | Truncate to 40 chars + `…` if longer |
| Status | `deliveryStatus` | `<Badge>`: `delivered` → `default`, `failed` → `destructive`, `pending` → `secondary` |
| Attempts | `attemptCount` | |
| Last Attempt | `latestAttemptAt` | `new Date(value).toLocaleString()` or `"—"` |
| HTTP Code | `latestAttemptStatusCode` | Value or `"—"` |

**Filter bar** (above table, `Card` + `CardContent` with `flex gap-2`):
- Source multi-select: `Popover` + `Command` (reuse `command.tsx`); selected count shown as badge on trigger button
- Endpoint multi-select: same pattern; options from all user endpoints
- Status filter: shadcn `Select` with options All / Pending / Delivered / Failed
- "Clear filters" `Button variant="ghost"` — rendered only when any filter is active

**Pagination** (below table):

```tsx
<div className="flex items-center justify-between pt-4">
  <span className="text-sm text-muted-foreground">{total} results</span>
  <div className="flex items-center gap-2">
    <Button variant="outline" size="sm" disabled={page === 1}
            onClick={() => setPage(p => p - 1)}>Previous</Button>
    <span className="text-sm px-2">Page {page}</span>
    <Button variant="outline" size="sm" disabled={page * PER_PAGE >= total}
            onClick={() => setPage(p => p + 1)}>Next</Button>
  </div>
</div>
```

Loading state: skeleton rows (7 columns × 5 rows). Error state: `<Alert variant="destructive">`. Empty state: centered muted text "No delivery logs found."

Wrap in `<Layout>` with `<Breadcrumb>` showing "Logs".

#### `App.tsx` routing

**Modify** `frontend/src/App.tsx`

1. `import LogsPage from "./pages/LogsPage"`
2. Add `/logs` route before `/sources` (wrapped in `ProtectedRoute` + `Layout`)

#### Sidebar navigation

**Modify** `frontend/src/components/AppSidebar.tsx`

Add to `navMain` after "Sources":

```typescript
{ title: "Logs", url: "/logs", icon: IconFileText }
```

Import `IconFileText` from `@tabler/icons-react`.

---

## Files Summary

| Action | File |
|---|---|
| Create | `src/Application/Value/LogEntry.php` |
| Create | `src/Application/Value/LogFilters.php` |
| Create | `src/Application/Value/LogsResult.php` |
| Create | `src/Application/Port/LogRepositoryPort.php` |
| Create | `src/Application/UseCase/Log/ListLogsUseCase.php` |
| Create | `src/Infrastructure/Persistence/DoctrineLogRepository.php` |
| Create | `src/Controller/Api/v1/Log/ListLogsController.php` |
| Create | `tests/Unit/Application/UseCase/Log/ListLogsUseCaseTest.php` |
| Create | `frontend/src/pages/LogsPage.tsx` |
| Modify | `frontend/src/App.tsx` |
| Modify | `frontend/src/components/AppSidebar.tsx` |

---

## Verification

| # | Check |
|---|---|
| 1 | `GET /api/v1/logs` returns HTTP 200 with `data`, `total`, `page`, `perPage` for authenticated user |
| 2 | `GET /api/v1/logs` returns HTTP 401 for unauthenticated request |
| 3 | `GET /api/v1/logs?endpoint_ids[]=X` returns only deliveries targeting endpoint X |
| 4 | `GET /api/v1/logs?source_ids[]=X` returns only deliveries for events from source X |
| 5 | `GET /api/v1/logs?status=failed` returns only failed deliveries |
| 6 | Combined filters `?source_ids[]=X&endpoint_ids[]=Y` intersect correctly |
| 7 | `per_page` clamped to 100 if request exceeds it |
| 8 | `total` in response reflects count without pagination |
| 9 | Second user's deliveries are not visible to first user |
| 10 | `php bin/phpunit tests/Unit/Application/UseCase/Log/` passes |
| 11 | `php bin/console debug:router \| grep logs` shows `GET /api/v1/logs` |
| 12 | `/logs` renders the data table with all 7 columns |
| 13 | Source filter populates from `GET /api/v1/sources` |
| 14 | Selecting a source filter re-fetches table data server-side |
| 15 | Selecting an endpoint filter re-fetches table data server-side |
| 16 | Status filter re-fetches data server-side |
| 17 | Status badge uses correct variant per status value |
| 18 | Pagination Previous/Next update the page and re-fetch |
| 19 | "Clear filters" resets all active filters and re-fetches |
| 20 | Sidebar "Logs" link navigates to `/logs` |
| 21 | Skeleton rows appear during loading |
| 22 | Empty state message shown when no results |
| 23 | `npm run build` succeeds with no TypeScript errors |
