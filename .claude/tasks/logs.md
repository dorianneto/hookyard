# Logs Page — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/logs.md` and `CLAUDE.md`.

---

## Phase 1 — Application Layer

- [x] **1.1** `src/Application/Value/LogEntry.php` — `final readonly` class; constructor params: `eventId` (`string`), `eventReceivedAt` (`\DateTimeImmutable`), `sourceName` (`string`), `sourceId` (`string`), `endpointId` (`string`), `endpointUrl` (`string`), `deliveryStatus` (`string`), `attemptCount` (`int`), `latestAttemptAt` (`?\DateTimeImmutable`), `latestAttemptStatusCode` (`?int`); no framework imports
- [x] **1.2** `src/Application/Value/LogFilters.php` — `final readonly` class; constructor params with defaults: `endpointIds` (`array = []`), `sourceIds` (`array = []`), `status` (`?string = null`), `page` (`int = 1`), `perPage` (`int = 20`); no framework imports
- [x] **1.3** `src/Application/Value/LogsResult.php` — `final readonly` class; `@param LogEntry[] $entries`; constructor params: `entries` (`array`), `total` (`int`), `page` (`int`), `perPage` (`int`); no framework imports
- [x] **1.4** `src/Application/Port/LogRepositoryPort.php` — interface with two methods: `findByUser(string $userId, LogFilters $filters): array` (`@return LogEntry[]`) and `countByUser(string $userId, LogFilters $filters): int`

---

## Phase 2 — Infrastructure: Persistence

- [x] **2.1** `src/Infrastructure/Persistence/DoctrineLogRepository.php` — implements `LogRepositoryPort`; constructor injects `Doctrine\DBAL\Connection`; private `buildBaseQuery(string $userId, LogFilters $filters): QueryBuilder` shared by both public methods:
  - Build `$aggSub` subquery via `$this->connection->createQueryBuilder()` selecting `da.event_id, da.endpoint_id, COUNT(*) AS attempt_count, MAX(da.attempted_at) AS latest_attempt_at` from `delivery_attempts da` grouped by `da.event_id, da.endpoint_id`
  - Main `$qb` from `event_endpoint_deliveries eed` with `JOIN events e`, `JOIN sources s`, `JOIN endpoints ep`, `LEFT JOIN ($aggSub->getSQL()) da_agg`
  - Base `WHERE s.user_id = :userId`; append `andWhere('ep.id IN (:endpointIds)')` when `$filters->endpointIds !== []`; append `andWhere('s.id IN (:sourceIds)')` when `$filters->sourceIds !== []`; append `andWhere('eed.status = :status')` when `$filters->status !== null`; use `ArrayParameterType::STRING` for array bindings
- [x] **2.2** `findByUser` — calls `buildBaseQuery`, adds `select(...)` for all 10 columns (correlated subquery `(SELECT da2.status_code FROM delivery_attempts da2 WHERE da2.event_id = eed.event_id AND da2.endpoint_id = eed.endpoint_id ORDER BY da2.attempt_number DESC LIMIT 1) AS latest_attempt_status_code`), `orderBy('e.received_at', 'DESC')`, `setMaxResults($filters->perPage)`, `setFirstResult(($filters->page - 1) * $filters->perPage)`, `fetchAllAssociative()`; maps rows via private `toLogEntry(array $row): LogEntry`
- [x] **2.3** `countByUser` — calls `buildBaseQuery`, adds `select('COUNT(*)')`, `fetchOne()`; returns `(int)`
- [x] **2.4** Private `toLogEntry(array $row): LogEntry` — casts `event_received_at` to `\DateTimeImmutable`; casts `latest_attempt_at` to `\DateTimeImmutable` or `null`; casts `latest_attempt_status_code` to `int` or `null`

---

## Phase 3 — Use Case

- [x] **3.1** `src/Application/UseCase/Log/ListLogsUseCase.php` — `#[WithMonologChannel('hookyard')]`; constructor: `LogRepositoryPort $logRepository`, `LoggerInterface $logger`; method `execute(string $requestId, string $userId, LogFilters $filters): LogsResult`; calls `findByUser` + `countByUser`; assembles and returns `LogsResult`; logs INFO on entry (`request_id`) and on success (`request_id`, `total`)

---

## Phase 4 — Controller

- [x] **4.1** `src/Controller/Api/v1/Log/ListLogsController.php` — `#[Route('/api/v1/logs', name: 'list_logs', methods: ['GET'])]`; `#[WithMonologChannel('hookyard')]`; constructor: `ListLogsUseCase`, `Security`, `LoggerInterface`; reads `request_id` from `$request->attributes->get('request_id')`; returns HTTP 401 if user is not authenticated; parses `endpoint_ids[]`, `source_ids[]`, `status`, `page`, `per_page` from query string; clamps `per_page` to max 100; builds `LogFilters`; calls use case; serializes `LogsResult` to `{ data, total, page, perPage }` JSON shape; `data` array maps each `LogEntry` with camelCase keys and ISO 8601 timestamps; logs INFO on request received

---

## Phase 5 — Tests

- [x] **5.1** `tests/Unit/Application/UseCase/Log/ListLogsUseCaseTest.php` — four test cases using `createMock(LogRepositoryPort::class)` and `new NullLogger()`:
  - `testExecuteReturnsLogsResult` — mock `findByUser` + `countByUser` return populated data; assert `LogsResult` fields propagated unchanged
  - `testExecuteWithNoFilters` — empty `LogFilters`; mock returns empty array and `0`; assert no exception and `LogsResult->entries === []`
  - `testExecuteWithEndpointFilter` — `LogFilters` with `endpointIds`; assert mock called with matching filters
  - `testExecuteWithStatusFilter` — `LogFilters` with `status = 'failed'`; assert mock called with matching filters
- [x] **5.2** Run `php bin/phpunit tests/Unit/Application/UseCase/Log/` — all tests pass

---

## Phase 6 — Frontend: Dependency

- [x] **6.1** From inside `frontend/`, run `npm install @tanstack/react-table`; verify it appears in `package.json` dependencies

---

## Phase 7 — Frontend: Logs Page

- [x] **7.1** `frontend/src/pages/LogsPage.tsx` — inline TypeScript interfaces `LogEntry` and `LogsResponse`; `useState` for `logs`, `loading`, `error`, `total`, `page`, `selectedEndpointIds`, `selectedSourceIds`, `selectedStatus`, `sources`, `endpointOptions`; `PER_PAGE = 20` constant
- [x] **7.2** On mount: fetch `GET /api/v1/sources` to populate source filter list; fetch `GET /api/v1/sources/{id}/endpoints` per source to build flat endpoint options list
- [x] **7.3** Data fetch effect (re-runs on `page`, `selectedEndpointIds`, `selectedSourceIds`, `selectedStatus` changes): `GET /api/v1/logs?page=N&per_page=20[&source_ids[]=...][&endpoint_ids[]=...][&status=...]`; updates `logs`, `total`
- [x] **7.4** Filter bar (`Card` + `CardContent` with `flex gap-2 flex-wrap`):
  - Source multi-select: `Popover` + `Command` + `CommandInput` + `CommandItem` per source; trigger button shows selected count as `Badge`; toggling an item adds/removes from `selectedSourceIds` and resets `page` to 1
  - Endpoint multi-select: same `Popover` + `Command` pattern; options from `endpointOptions`; toggling adds/removes from `selectedEndpointIds` and resets `page` to 1
  - Status filter: shadcn `Select` with options All / Pending / Delivered / Failed; change resets `page` to 1
  - "Clear filters" `Button variant="ghost"`: rendered only when any filter is active; resets all three filter states and `page` to 1
- [x] **7.5** TanStack table: `useReactTable` with `getCoreRowModel()`; columns: Received At (`new Date(value).toLocaleString()`), Source, Endpoint URL (truncate to 40 chars + `…`), Status (`<Badge>` variant: `delivered` → `default`, `failed` → `destructive`, `pending` → `secondary`), Attempts, Last Attempt (`toLocaleString()` or `"—"`), HTTP Code (value or `"—"`)
- [x] **7.6** Loading state: skeleton rows — `Array.from({ length: 5 })` rows × 7 `<TableCell><Skeleton className="h-4 w-full" /></TableCell>` columns
- [x] **7.7** Error state: `<Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>`
- [x] **7.8** Empty state (no loading, no error, `logs.length === 0`): centered `<p className="text-center text-muted-foreground py-8">No delivery logs found.</p>`
- [x] **7.9** Pagination bar below table (`flex items-center justify-between pt-4`): "{total} results" span + Previous/Next `Button variant="outline" size="sm"` (Previous disabled when `page === 1`; Next disabled when `page * PER_PAGE >= total`) + "Page {page}" label
- [x] **7.10** Wrap page in `<Layout>` with `<Breadcrumb>` showing "Logs"

---

## Phase 8 — Frontend: Routing & Navigation

- [x] **8.1** `frontend/src/App.tsx` — `import LogsPage from "./pages/LogsPage"`; add `/logs` route (wrapped in `ProtectedRoute` + `Layout`) before the `/sources` route
- [x] **8.2** `frontend/src/components/AppSidebar.tsx` — add `{ title: "Logs", url: "/logs", icon: IconFileText }` to `navMain` after the Sources entry; import `IconFileText` from `@tabler/icons-react`
- [x] **8.3** Run `npm run build` from `frontend/` — must complete with no TypeScript errors

---

## Dependency Order

```
Phase 1 (value objects + port)
  └─ Phase 2 (repository)
       └─ Phase 3 (use case)
            └─ Phase 4 (controller)
                 └─ Phase 5 (tests)

Phase 6 (npm dependency)
  └─ Phase 7 (logs page)
       └─ Phase 8 (routing + navigation)
```
