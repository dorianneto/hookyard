# Audit Module — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/audit-module.md` and `CLAUDE.md`.

---

## Phase 1 — Application Layer: Events, Ports, Value Objects

- [x] **1.1** `src/Application/Event/AuditableActionEvent.php` — Create a `final readonly` class with constructor params: `string $userId`, `string $requestId`, `string $action` (`create|delete|edit`), `string $resource` (`source|endpoint|account`), `?string $resourceId`, `array $metadata`. No Symfony or Doctrine imports.

- [x] **1.2** `src/Application/Port/AuditEventDispatcherPort.php` — Create interface with one method: `dispatch(AuditableActionEvent $event): void`.

- [x] **1.3** `src/Application/Port/AuditRepositoryPort.php` — Create interface with three methods: `findByUser(string $userId, AuditFilters $filters): array` (returns `AuditEntry[]`), `countByUser(string $userId, AuditFilters $filters): int`, `save(AuditLog $auditLog): void` (accepts `App\Domain\AuditLog`).

- [x] **1.4** `src/Application/Value/AuditEntry.php` — Create `final readonly` class with: `string $id`, `string $action`, `string $resource`, `?string $resourceId`, `array $metadata`, `\DateTimeImmutable $createdAt`.

- [x] **1.5** `src/Application/Value/AuditFilters.php` — Create `final readonly` class with: `array $actions = []`, `array $resources = []`, `?\DateTimeImmutable $dateFrom = null`, `?\DateTimeImmutable $dateTo = null`, `int $page = 1`, `int $perPage = 20`.

- [x] **1.6** `src/Application/Value/AuditResult.php` — Create `final readonly` class with: `array $entries` (typed as `AuditEntry[]` in docblock), `int $total`, `int $page`, `int $perPage`.

## Phase 2 — Application Layer: Use Case

- [x] **2.1** `src/Application/UseCase/Audit/ListAuditLogsUseCase.php` — Create `final` class with `#[WithMonologChannel('hookyard')]`. Constructor: `AuditRepositoryPort $auditRepository`, `LoggerInterface $logger`. Method `execute(string $requestId, string $userId, AuditFilters $filters): AuditResult` — log INFO on entry and on return (with `total`), call `findByUser` + `countByUser`, return `AuditResult`.

## Phase 3 — Domain Entity, Infrastructure: Doctrine Entity + Migration

- [x] **3.0** `src/Domain/AuditLog.php` — Create `final readonly` class with constructor params: `string $id`, `string $userId`, `string $action`, `string $resource`, `?string $resourceId`, `array $metadata`, `\DateTimeImmutable $createdAt`. No Symfony or Doctrine imports. Getters for all fields. This is the domain object passed across the `AuditRepositoryPort::save()` boundary.

- [x] **3.1** `src/Entity/AuditLog.php` — Create Doctrine entity mapped to `audit_logs` table. Fields: `id` (VARCHAR UUID, PK), `user` (`#[ORM\ManyToOne(targetEntity: User::class)]` + `#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]` — explicit FK column name), `action` (VARCHAR 32), `resource` (VARCHAR 64), `resourceId` (VARCHAR UUID, nullable, mapped `resource_id`), `metadata` (JSON type), `createdAt` (DATETIME_IMMUTABLE, mapped `created_at`). Add `#[ORM\Index(columns: ['user_id', 'created_at'])]` and `#[ORM\Index(columns: ['action'])]` on the entity class. Add getter/setter for each field; `getUser(): User` / `setUser(User $user): void`. Add `fromDomain(Domain\AuditLog $log, User $user): self` static factory.

- [ ] **3.2** Generate and commit migration — run `php bin/console doctrine:migrations:diff` inside the app container, verify the generated SQL creates `audit_logs` with all columns and indexes, then commit the migration file alongside the entity.

## Phase 4 — Infrastructure: Repository, Dispatcher, Listener

- [x] **4.1** `src/Infrastructure/Persistence/DoctrineAuditRepository.php` — Implement `AuditRepositoryPort`. Constructor: `EntityManagerInterface`. `findByUser`: build QueryBuilder on `Entity\AuditLog` alias `a`, filter/order/paginate, map each entity to `AuditEntry`. `countByUser`: same filters, `SELECT COUNT(a.id)`. `save(Domain\AuditLog $auditLog)`: resolve `User` reference via `getReference(User::class, $auditLog->getUserId())`, call `Entity\AuditLog::fromDomain($auditLog, $user)`, then `persist` + `flush`.

- [x] **4.2** `src/Infrastructure/EventDispatcher/SymfonyAuditEventDispatcher.php` — Implement `AuditEventDispatcherPort`. Constructor: `EventDispatcherInterface $eventDispatcher`. `dispatch(AuditableActionEvent $event): void` — call `$this->eventDispatcher->dispatch($event)`.

- [x] **4.3** `src/Infrastructure/EventListener/RecordAuditEntryListener.php` — Add `#[AsEventListener(event: AuditableActionEvent::class)]` and `#[WithMonologChannel('hookyard')]`. Constructor: `AuditRepositoryPort $auditRepository`, `LoggerInterface $logger` (no `EntityManagerInterface` — the listener has no Doctrine dependency). `__invoke(AuditableActionEvent $event): void` — wrap entire body in `try/catch(\Throwable $e)`. On success: build `Domain\AuditLog` with `id = Uuid::v7()->toRfc4122()`, `userId`, all other fields from the event; call `$this->auditRepository->save($log)`; log DEBUG. On catch: log ERROR — do NOT rethrow.

## Phase 5 — Controller

- [x] **5.1** `src/Controller/Api/v1/Audit/ListAuditLogsController.php` — Create `final` class extending `AbstractController` with `#[Route('/api/v1/audit', name: 'audit_list', methods: ['GET'])]` and `#[WithMonologChannel('hookyard')]`. Constructor: `ListAuditLogsUseCase $useCase`, `LoggerInterface $logger`. `__invoke(Request $request): JsonResponse`: read `request_id` from `$request->attributes->get('request_id')`; parse `date_from` / `date_to` with `\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, ...)`, return `json(['error' => '...'], 400)` on parse failure; build `AuditFilters` from query params (`action[]`, `resource[]`, `date_from`, `date_to`, `page`, `per_page` clamped max 100); call `$this->useCase->execute()`; return 200 JSON with `data`, `total`, `page`, `per_page`.

## Phase 6 — Modify Existing Use Cases

- [x] **6.1** `src/Application/UseCase/Source/CreateSourceUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. After `$this->sourceRepository->save($source)` succeeds, dispatch `new AuditableActionEvent(userId: $userId, requestId: $requestId, action: 'create', resource: 'source', resourceId: $id, metadata: ['name' => $name])`.

- [x] **6.2** `src/Application/UseCase/Source/DeleteSourceUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. After `$this->sourceRepository->delete($id, $userId)` succeeds, dispatch with `action: 'delete'`, `resource: 'source'`, `resourceId: $id`, `metadata: ['source_id' => $id]`.

- [x] **6.3** `src/Application/UseCase/Endpoint/AddEndpointUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. After `$this->endpointRepository->save($endpoint)` succeeds, dispatch with `action: 'create'`, `resource: 'endpoint'`, `resourceId: $id`, `metadata: ['url' => $url, 'source_id' => $sourceId]`.

- [x] **6.4** `src/Application/UseCase/Endpoint/DeleteEndpointUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. Capture `$endpoint->getUrl()` and `$endpoint->getSourceId()` before the transaction. After the transaction closure completes, dispatch with `action: 'delete'`, `resource: 'endpoint'`, `resourceId: $id`, `metadata: ['url' => $endpointUrl, 'source_id' => $sourceId]`.

- [x] **6.5** `src/Application/UseCase/RegisterUserUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. After the user is persisted, dispatch with `action: 'create'`, `resource: 'account'`, `resourceId: $userId`, `metadata: ['email' => $email]`.

- [x] **6.6** `src/Application/UseCase/UpdateAccountUseCase.php` — Add `AuditEventDispatcherPort $auditDispatcher` to constructor. Before calling `$this->userRepository->save($updated)`, compute `$changedFields`: append `'name'` if `$name !== $user->getName()`, append `'password'` if `$newPasswordHash !== null`. After save, dispatch with `action: 'edit'`, `resource: 'account'`, `resourceId: $userId`, `metadata: ['changed_fields' => $changedFields]`. Never include password values.

## Phase 7 — Unit Tests

- [x] **7.1** `tests/Unit/Application/UseCase/Audit/ListAuditLogsUseCaseTest.php` — Assert: `findByUser` and `countByUser` are called with the correct filters; the returned `AuditResult` has matching `entries`, `total`, `page`, `perPage`. Use `createMock(AuditRepositoryPort::class)` and `new NullLogger()`.

- [x] **7.2** `tests/Unit/Infrastructure/EventListener/RecordAuditEntryListenerTest.php` — Assert: on a valid `AuditableActionEvent`, `AuditRepositoryPort::save()` is called once with a `Domain\AuditLog` whose `getUserId()`, `getAction()`, `getResource()`, and `getResourceId()` match the event fields. No `EntityManagerInterface` mock needed. Assert: when `save()` throws, the listener does not propagate the exception.

- [x] **7.3** `tests/Unit/Infrastructure/EventDispatcher/SymfonyAuditEventDispatcherTest.php` — Assert: `EventDispatcherInterface::dispatch()` is called once with the `AuditableActionEvent` passed to `dispatch()`.

- [x] **7.4** `tests/Unit/Application/UseCase/Source/CreateSourceUseCaseTest.php` — Add a test asserting `AuditEventDispatcherPort::dispatch()` is called once with `action='create'` and `resource='source'` after a successful save. Add a test asserting `dispatch()` is NOT called when `sourceRepository->save()` throws.

- [x] **7.5** `tests/Unit/Application/UseCase/Source/DeleteSourceUseCaseTest.php` — Add a test asserting `dispatch()` is called once with `action='delete'`, `resource='source'` after successful delete.

- [x] **7.6** `tests/Unit/Application/UseCase/Endpoint/AddEndpointUseCaseTest.php` — Add a test asserting `dispatch()` is called once with `action='create'`, `resource='endpoint'` after successful save.

- [x] **7.7** `tests/Unit/Application/UseCase/Endpoint/DeleteEndpointUseCaseTest.php` — Add a test asserting `dispatch()` is called once with `action='delete'`, `resource='endpoint'` after successful transaction.

- [x] **7.8** `tests/Unit/Application/UseCase/UpdateAccountUseCaseTest.php` — Add tests: changing only name → `changed_fields=['name']`; changing password → `changed_fields=['password']`; changing both → `changed_fields=['name','password']`; no changes → `changed_fields=[]`.

- [x] **7.9** `tests/Unit/Application/UseCase/RegistrationUseCaseTest.php` — Add a test asserting `dispatch()` is called once with `action='create'`, `resource='account'` after successful registration.

## Phase 8 — Frontend

- [x] **8.1** `frontend/src/pages/AuditPage.tsx` — Create page component wrapped in `<Layout>`. State: `entries`, `total`, `page` (default 1), `loading`, `error`, `selectedActions: string[]` (default `[]`), `dateFrom: string` (default `''`), `dateTo: string` (default `''`). Fetch `GET /api/v1/audit` on mount and on filter/page change (via `useEffect` with deps `[page, selectedActions, dateFrom, dateTo]`). Filter bar: multi-select `Select` for actions with options `create`, `delete`, `edit`; two `<input type="date">` fields. Table: `Table` + `TableHeader` + `TableBody`, columns **When** / **Action** / **Resource** / **Details**. Badge variants: `create` → `default`, `delete` → `destructive`, `edit` → `secondary`. **When** format: `new Date(created_at).toLocaleString()`. **Details**: `Object.entries(metadata).map(([k,v]) => \`${k}=${v}\`).join(', ')`. Pagination row: `Button` prev/next + label. Empty row when `entries.length === 0`. `Skeleton` rows when loading. `Alert variant="destructive"` on error.

- [x] **8.2** `frontend/src/App.tsx` — Import `AuditPage` and add `<Route path="/audit" element={<AuditPage />} />` inside the protected route group alongside the other protected routes.

- [x] **8.3** `frontend/src/components/AppSidebar.tsx` — Import `IconClipboardList` from `@tabler/icons-react`. Add `{ title: "Audit", url: "/audit", icon: IconClipboardList }` to `data.navSecondary` as the first item (before "Get Help").

- [x] **8.4** `frontend/src/components/NavSecondary.tsx` — Inside the `CommandGroup` in the `CommandDialog`, add a new `CommandItem` with `onSelect={() => { setOpen(false); navigate("/audit"); }}` and label `Audit`.
