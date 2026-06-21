# Subscription Management — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/subscription-management.md` and `CLAUDE.md`.

---

## Phase 1 — Domain Layer

- [x] **1.1** `src/Domain/User.php` — Add nullable `stripeSubscriptionId` constructor parameter after `stripeCustomerId`; add `getStripeSubscriptionId(): ?string` getter. No Symfony/Doctrine imports.

- [x] **1.2** `src/Domain/Exception/NoActiveSubscriptionException.php` — Create `final class NoActiveSubscriptionException extends \RuntimeException {}` in namespace `App\Domain\Exception`.

- [x] **1.3** `src/Domain/Exception/AlreadyOnPlanException.php` — Create `final class AlreadyOnPlanException extends \RuntimeException {}` in namespace `App\Domain\Exception`.

---

## Phase 2 — Infrastructure: Persistence

- [x] **2.1** `src/Entity/User.php` — Add `#[ORM\Column(length: 255, nullable: true)] private ?string $stripeSubscriptionId = null;` column. Add getter `getStripeSubscriptionId(): ?string` and setter `setStripeSubscriptionId(?string $id): void`. Wire `stripeSubscriptionId` in `toDomain()` (pass to domain constructor), `fromDomain()` (set from domain), and `updateFromDomain()` — mirror the existing `stripeCustomerId` pattern exactly.

- [x] **2.2** Generate and commit migration — Run `php bin/console doctrine:migrations:diff` to produce the migration adding `stripe_subscription_id VARCHAR(255) NULL` to the `users` table. Commit the generated file alongside the entity changes.

---

## Phase 3 — Application Port & Stripe Adapter

- [x] **3.1** `src/Application/Port/StripeServicePort.php` — Add method signature `public function cancelSubscription(string $subscriptionId): void;` to the interface.

- [x] **3.2** `src/Infrastructure/Stripe/StripeService.php` — Implement `cancelSubscription(string $subscriptionId): void` by calling `$this->client->subscriptions->cancel($subscriptionId);`.

---

## Phase 4 — Webhook Update

- [x] **4.1** `src/Application/UseCase/HandleStripeWebhookUseCase.php` — Add `planId: string` and `stripeSubscriptionId: ?string` parameters to `execute()`. When building the updated `User`, set `planId: $planId` and `stripeSubscriptionId: $stripeSubscriptionId` in addition to the existing `stripeCustomerId` and `status: 'active'` fields.

- [x] **4.2** `src/Controller/Api/v1/Stripe/WebhookController.php` — Extract `$session->metadata->plan_id` and `$session->subscription` from the completed session object and pass them as `planId` and `stripeSubscriptionId` to `HandleStripeWebhookUseCase::execute()`.

---

## Phase 5 — Use Cases

- [x] **5.1** `src/Application/UseCase/GetSubscriptionUseCase.php` — Create use case with constructor `(UserRepositoryPort $userRepository, PlanRepositoryPort $planRepository, LoggerInterface $logger)`. Method signature: `execute(string $requestId, string $userId): array`. Implementation: fetch user via `$userRepository->findById($userId)`; fetch all plans via `$planRepository->findAll()`; find current plan by matching `$user->getPlanId()` against plan list; log INFO with `request_id` and `user_id`; return `['current_plan' => ..., 'available_plans' => [...], 'status' => $user->getStatus()]`. Apply `#[WithMonologChannel('hookyard')]`.

- [x] **5.2** `src/Application/UseCase/ChangePlanUseCase.php` — Create use case with constructor `(UserRepositoryPort $userRepository, PlanRepositoryPort $planRepository, StripeServicePort $stripeService, LoggerInterface $logger)`. Method signature: `execute(string $requestId, string $userId, string $planId, string $successUrl, string $cancelUrl): string`. Logic: (1) fetch user; (2) if `$user->getPlanId() === $planId` throw `AlreadyOnPlanException`; (3) `$plan = $planRepository->findById($planId)` — if null throw `PlanNotFoundException`; (4) if `$plan->getStripePriceId() === null` throw `PlanNotConfiguredException`; (5) if `$user->getStripeSubscriptionId() !== null` call `$stripeService->cancelSubscription($user->getStripeSubscriptionId())`; (6) return `$stripeService->createCheckoutSession(...)`. Apply `#[WithMonologChannel('hookyard')]`.

- [x] **5.3** `src/Application/UseCase/CancelSubscriptionUseCase.php` — Create use case with constructor `(UserRepositoryPort $userRepository, StripeServicePort $stripeService, LoggerInterface $logger)`. Method signature: `execute(string $requestId, string $userId): void`. Logic: (1) fetch user; (2) if `$user->getStripeSubscriptionId() === null` throw `NoActiveSubscriptionException`; (3) call `$stripeService->cancelSubscription($user->getStripeSubscriptionId())`; (4) build updated `User` with `status: 'cancelled'`, `planId: null`, `stripeSubscriptionId: null`; (5) `$userRepository->save($updatedUser)`; (6) log INFO. Apply `#[WithMonologChannel('hookyard')]`.

---

## Phase 6 — Controllers

- [x] **6.1** `src/Controller/Api/v1/Subscription/GetSubscriptionController.php` — Route: `#[Route('/api/v1/subscription', name: 'api_v1_subscription_get', methods: ['GET'])]`. Inject `GetSubscriptionUseCase` and `LoggerInterface`. Read `request_id` from request attributes and user ID from Symfony Security token. Call `GetSubscriptionUseCase::execute()`, return `JsonResponse` with data, HTTP 200. Apply `#[WithMonologChannel('hookyard')]`.

- [x] **6.2** `src/Controller/Api/v1/Subscription/ChangePlanController.php` — Route: `#[Route('/api/v1/subscription/change-plan', name: 'api_v1_subscription_change_plan', methods: ['POST'])]`. Inject `ChangePlanUseCase` and `LoggerInterface`. Decode JSON body; extract `plan_id`. Construct `successUrl` and `cancelUrl`. Call `ChangePlanUseCase::execute(...)`. Catch exceptions and return appropriate error responses. Return 200 `{"checkout_url": "..."}` on success. Apply `#[WithMonologChannel('hookyard')]`.

- [x] **6.3** `src/Controller/Api/v1/Subscription/CancelSubscriptionController.php` — Route: `#[Route('/api/v1/subscription', name: 'api_v1_subscription_cancel', methods: ['DELETE'])]`. Inject `CancelSubscriptionUseCase` and `LoggerInterface`. Call `CancelSubscriptionUseCase::execute(...)`. Catch `NoActiveSubscriptionException` → 422 `{"error":"no_active_subscription"}`. Return 204 on success. Apply `#[WithMonologChannel('hookyard')]`.

---

## Phase 7 — Unit Tests

- [x] **7.1** `tests/Unit/Application/UseCase/GetSubscriptionUseCaseTest.php` — Test: happy path returns correct array shape. Mock `UserRepositoryPort` and `PlanRepositoryPort` with `createMock()`. Use `new NullLogger()`. Verify `current_plan` matches user's `planId`, `available_plans` contains all returned plans, `status` matches user's status.

- [x] **7.2** `tests/Unit/Application/UseCase/ChangePlanUseCaseTest.php` — Tests: (a) happy path — `cancelSubscription` called when user has existing subscription ID, returns checkout URL; (b) `AlreadyOnPlanException` thrown when `planId` matches user's current plan; (c) `PlanNotFoundException` thrown when plan not found; (d) `PlanNotConfiguredException` thrown when plan has no `stripePriceId`; (e) `cancelSubscription` NOT called when user has no existing subscription. Mock `UserRepositoryPort`, `PlanRepositoryPort`, and `StripeServicePort`. Use `new NullLogger()`.

- [x] **7.3** `tests/Unit/Application/UseCase/CancelSubscriptionUseCaseTest.php` — Tests: (a) happy path — `cancelSubscription` called, user saved with `status='cancelled'`, `planId=null`, `stripeSubscriptionId=null`; (b) `NoActiveSubscriptionException` thrown when user has no `stripeSubscriptionId`. Mock `UserRepositoryPort` and `StripeServicePort`. Use `new NullLogger()`.

---

## Phase 8 — Frontend

- [x] **8.1** `frontend/src/contexts/AuthContext.tsx` — Add `'cancelled'` to the `status` union type: `status: 'pending_payment' | 'active' | 'cancelled'`.

- [x] **8.2** `frontend/src/components/NavUser.tsx` — Import `IconCreditCard` from `@tabler/icons-react`. Add a new `DropdownMenuItem` with `<Link to="/subscription"><IconCreditCard />Subscription</Link>` inside `DropdownMenuGroup`, positioned between "Account" and "Audit".

- [x] **8.3** `frontend/src/pages/SubscriptionPage.tsx` — Create the subscription page component with current plan card, plan comparison grid, and cancel subscription dialog.

- [x] **8.4** `frontend/src/App.tsx` — Import `SubscriptionPage` and add protected route for `/subscription`.
