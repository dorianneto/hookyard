# Stripe Plan Registration — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/stripe-plan-registration.md` and `CLAUDE.md`.

---

## Phase 0 — Dependencies

- [x] **0.1** Install `stripe/stripe-php` via Composer inside the app container: `composer require stripe/stripe-php`. Verify it appears in `composer.json` and `composer.lock`.
- [x] **0.2** Add `STRIPE_SECRET_KEY=sk_test_...` to `.env` (placeholder values). Do NOT commit real keys.

---

## Phase 1 — Domain Layer

- [x] **1.1** `src/Domain/Plan.php` — Add `private ?string $stripePriceId = null` as the 4th constructor parameter (before `createdAt`). Add getter `getStripePriceId(): ?string`. No Symfony/Doctrine imports.

- [x] **1.2** `src/Domain/User.php` — Add `private ?string $stripeCustomerId = null` as the last constructor parameter. Add getter `getStripeCustomerId(): ?string`. Also add `private string $status = 'pending_payment'` and getter `getStatus(): string`. No Symfony/Doctrine imports.

- [x] **1.3** `src/Domain/Exception/PlanNotFoundException.php` — Create: `final class PlanNotFoundException extends \RuntimeException {}`. Namespace `App\Domain\Exception`.

- [x] **1.4** `src/Domain/Exception/PlanNotConfiguredException.php` — Create: `final class PlanNotConfiguredException extends \RuntimeException {}`. Namespace `App\Domain\Exception`. Thrown when a Plan exists but has a null `stripe_price_id`.

- [x] **1.5** `src/Domain/Exception/AccountAlreadyActiveException.php` — Create: `final class AccountAlreadyActiveException extends \RuntimeException {}`. Namespace `App\Domain\Exception`. Thrown by `ResumeRegistrationUseCase` when the user status is already `active`.

---

## Phase 2 — Doctrine Entities

- [x] **2.1** `src/Entity/Plan.php` — Add `#[ORM\Column(name: 'stripe_price_id', type: Types::STRING, nullable: true)]` `private ?string $stripePriceId = null`. Update constructor to accept `?string $stripePriceId = null`. Update `toDomain()` to pass `stripePriceId: $this->stripePriceId`.

- [x] **2.2** `src/Entity/User.php` — Add `#[ORM\Column(name: 'stripe_customer_id', type: Types::STRING, nullable: true)]` `private ?string $stripeCustomerId = null`. Also add `#[ORM\Column(name: 'status', type: Types::STRING, length: 50, options: ['default' => 'pending_payment'])]` `private string $status = 'pending_payment'`. Update `fromDomain()` to also set `$entity->stripeCustomerId` and `$entity->status`. Update `toDomain()` to pass both `stripeCustomerId: $this->stripeCustomerId` and `status: $this->status`. Also updated `DoctrineUserRepository.save()` to sync `stripeCustomerId`, `status`, and `plan` on updates.

- [x] **2.3** Generate and commit migration: run `php bin/console doctrine:migrations:diff` inside the container, review the generated file (expect `ALTER TABLE plans ADD stripe_price_id`, `ALTER TABLE users ADD stripe_customer_id`, `ALTER TABLE users ADD status VARCHAR(50) NOT NULL DEFAULT 'pending_payment'`), then run `php bin/console doctrine:migrations:migrate`. If an existing dev DB has real users, also run: `UPDATE users SET status = 'active' WHERE plan_id IS NOT NULL`. Commit the migration file alongside the entity changes.

---

## Phase 3 — Application Ports

- [x] **3.1** `src/Application/Port/PlanRepositoryPort.php` — Add two method signatures: `public function findById(string $id): ?Plan;` and `/** @return Plan[] */ public function findAll(): array;`. Existing `findByUserId` stays unchanged.

- [x] **3.2** `src/Application/Port/StripeServicePort.php` — Create interface with a single method: `public function createCheckoutSession(string $stripePriceId, string $userId, string $planId, string $successUrl, string $cancelUrl): string;`. Namespace `App\Application\Port`. Zero Stripe imports.

---

## Phase 4 — Infrastructure: Persistence

- [x] **4.1** `src/Infrastructure/Persistence/DoctrinePlanRepository.php` — Implement `findById(string $id): ?Plan`: use `$this->entityManager->find(PlanEntity::class, $id)` and call `->toDomain()` if non-null. Implement `findAll(): array`: use `$this->entityManager->getRepository(PlanEntity::class)->findAll()` mapped through `toDomain()`.

---

## Phase 5 — Infrastructure: Stripe

- [x] **5.1** `src/Infrastructure/Stripe/StripeService.php` — Create class implementing `StripeServicePort`. Constructor takes `string $secretKey` and instantiates `\Stripe\StripeClient`. `createCheckoutSession()` calls `$this->client->checkout->sessions->create([...])` with `mode: 'subscription'`, `line_items`, `metadata: ['user_id' => $userId, 'plan_id' => $planId]`, `success_url`, `cancel_url`, and returns `$session->url`.

- [x] **5.2** `config/services.yaml` — Register `App\Infrastructure\Stripe\StripeService` with `$secretKey: '%env(STRIPE_SECRET_KEY)%'`. Register `App\Controller\Api\v1\Stripe\WebhookController` with `$webhookSecret: '%env(STRIPE_WEBHOOK_SECRET)%'`.

---

## Phase 6 — Use Cases

- [x] **6.1** `src/Application/UseCase/ListPlansUseCase.php` — Create. Constructor: `PlanRepositoryPort $planRepository`, `LoggerInterface $logger`. Decorated with `#[WithMonologChannel('hookyard')]`. `execute(string $requestId): array` logs `info('List plans', ['request_id' => $requestId])` and returns `$this->planRepository->findAll()`.

- [x] **6.2** `src/Application/UseCase/RegisterUserUseCase.php` — Modify. Add `PlanRepositoryPort $planRepository` and `StripeServicePort $stripeService` to constructor. Change `execute()` signature to: `execute(string $requestId, string $id, string $email, string $passwordHash, string $planId, string $successUrl, string $cancelUrl, ?string $name = null): string`. Logic: (1) validate plan first — call `findById($planId)`, throw `PlanNotFoundException` if null, throw `PlanNotConfiguredException` if `getStripePriceId() === null`; (2) check email — if user exists AND `status === 'active'`, throw `EmailAlreadyTakenException`; if user exists AND `status === 'pending_payment'`, reuse the existing user (no save, no audit event), set `$userId = $existingUser->getId()`; if no user exists, create `User` with `planId: $planId`, `status: 'pending_payment'`, save, dispatch audit event; (3) call `createCheckoutSession()` using `$userId`; (4) return checkout URL.

- [x] **6.3** `src/Application/UseCase/HandleStripeWebhookUseCase.php` — Create. Constructor: `UserRepositoryPort $userRepository`, `LoggerInterface $logger`. Decorated with `#[WithMonologChannel('hookyard')]`. `execute(string $requestId, string $userId, string $stripeCustomerId): void` (no `$planId` — it was already set at registration). Fetches user by `$userId`; if not found logs WARNING and returns. Creates a new `User` instance copying all fields but with `stripeCustomerId: $stripeCustomerId` and `status: 'active'`. Saves via `userRepository->save()`. Logs info on success.

- [x] **6.4** `src/Application/UseCase/ResumeRegistrationUseCase.php` — Create. Constructor: `UserRepositoryPort $userRepository`, `PlanRepositoryPort $planRepository`, `StripeServicePort $stripeService`, `LoggerInterface $logger`. Decorated with `#[WithMonologChannel('hookyard')]`. `execute(string $requestId, string $userId, string $successUrl, string $cancelUrl): string`. Fetches user by `$userId`. If `$user->getStatus() === 'active'`, throw `AccountAlreadyActiveException`. Calls `$planRepository->findById($user->getPlanId() ?? '')`. If null or `getStripePriceId() === null`, throw `PlanNotConfiguredException`. Calls `createCheckoutSession()` and returns the URL.

---

## Phase 7 — Controllers

- [x] **7.1** `src/Controller/Api/v1/Plan/ListPlansController.php` — Create. `#[Route('/plans', name: 'api_v1_plans_list', methods: ['GET'])]`, `#[WithMonologChannel('hookyard')]`. Constructor: `ListPlansUseCase $listPlansUseCase`, `LoggerInterface $logger`. `__invoke(Request $request): JsonResponse` reads `request_id`, calls use case, maps plans to `['id', 'name', 'monthly_request_limit']` (no `stripe_price_id`), returns `JsonResponse(['data' => [...]])`.

- [x] **7.2** `src/Controller/Api/v1/RegistrationController.php` — Modify. (a) Add validation for `plan_id` (NotBlank, `plan_id is required.`). (b) Build `$successUrl = $request->getSchemeAndHttpHost() . '/register/success'` and `$cancelUrl = $request->getSchemeAndHttpHost() . '/register/cancel'`. (c) Update `registerUserUseCase->execute()` call with new params. (d) Catch `PlanNotFoundException` → 422 `{"error": "Plan not found."}`. (e) Catch `PlanNotConfiguredException` → log ERROR, return 500. (f) Return `JsonResponse(['checkout_url' => $checkoutUrl], 201)`.

- [x] **7.3** `src/Controller/Api/v1/Stripe/WebhookController.php` — Create. `#[Route('/stripe/webhook', name: 'api_v1_stripe_webhook', methods: ['POST'])]`, `#[WithMonologChannel('hookyard')]`. Constructor: `HandleStripeWebhookUseCase $handleWebhookUseCase`, `LoggerInterface $logger`, `string $webhookSecret`. `__invoke()`: reads raw body via `$request->getContent()` and `Stripe-Signature` header. Calls `\Stripe\Webhook::constructEvent()` in try/catch — on `SignatureVerificationException` return 400. Ignores non-`checkout.session.completed` events (return 200). Extracts `$session->metadata->user_id` and `$session->customer` (no `plan_id` needed — plan was set at registration) and calls the use case. Returns 200 `{}`.

- [x] **7.4** `src/Controller/Api/v1/Stripe/ResumeRegistrationController.php` — Create. `#[Route('/register/resume', name: 'api_v1_register_resume', methods: ['POST'])]`, `#[WithMonologChannel('hookyard')]`. Constructor: `ResumeRegistrationUseCase $resumeUseCase`, `LoggerInterface $logger`. `__invoke()`: reads `request_id`, retrieves the current authenticated user's ID from the security context (adapt to how existing authenticated controllers do it), builds `$successUrl` and `$cancelUrl`. Catch `AccountAlreadyActiveException` → return 409. Catch `PlanNotConfiguredException` → log ERROR, return 422. Returns 200 `{"checkout_url": $url}`.

- [x] **7.5** `src/Controller/MeController.php` — Modify. Add `"status": $user->getStatus()` to the JSON response body. Exact property name must be `status`.

---

## Phase 8 — Security & Config

- [x] **8.1** `config/packages/security.yaml` — Add `/api/v1/plans` and `/api/v1/stripe/webhook` to the firewall's public access list (access_control or firewall pattern), ensuring they don't require JWT authentication. `/api/v1/register/resume` must remain behind the JWT firewall.

---

## Phase 9 — Unit Tests

- [x] **9.1** `tests/Unit/Application/UseCase/ListPlansUseCaseTest.php` — Create. Test: `execute()` calls `planRepository->findAll()` and returns the result. Use `createMock(PlanRepositoryPort::class)` and `new NullLogger()`.

- [x] **9.2** `tests/Unit/Application/UseCase/RegisterUserUseCaseTest.php` — Modify. Update `execute()` call signatures throughout. Add new tests: (a) throws `PlanNotFoundException` when plan not found; (b) throws `PlanNotConfiguredException` when `stripe_price_id` is null; (c) returns checkout URL on success; (d) creates user with `planId` set and `status = 'pending_payment'`; (e) calls `stripeService->createCheckoutSession()` with correct args; (f) re-registration with `pending_payment` email reuses existing user and returns new checkout URL (no save called for a new entity, no audit event dispatched); (g) re-registration with `active` email throws `EmailAlreadyTakenException`. Use `createMock(StripeServicePort::class)` and `createMock(PlanRepositoryPort::class)`.

- [x] **9.3** `tests/Unit/Application/UseCase/HandleStripeWebhookUseCaseTest.php` — Create. Tests: (a) updates user's `stripeCustomerId` and `status = 'active'` when user found; (b) returns early (no error, no save) when user not found. Use `createMock(UserRepositoryPort::class)` and `new NullLogger()`.

- [x] **9.4** `tests/Unit/Application/UseCase/ResumeRegistrationUseCaseTest.php` — Create. Tests: (a) returns checkout URL for `pending_payment` user with valid plan; (b) throws `AccountAlreadyActiveException` when user status is `active`; (c) throws `PlanNotConfiguredException` when user's plan has no `stripe_price_id`. Use `createMock(UserRepositoryPort::class)`, `createMock(PlanRepositoryPort::class)`, `createMock(StripeServicePort::class)`, `new NullLogger()`.

---

## Phase 10 — Frontend

- [x] **10.1** `frontend/src/contexts/AuthContext.tsx` — Modify. Add `status: 'pending_payment' | 'active'` to the `User` interface. The `/me` API now returns `status` — map it in the `login()` and `updateUser()` functions when storing the user object.

- [x] **10.2** `frontend/src/pages/RegisterPage.tsx` — Modify. (a) Add `useState<Plan[]>([])` for plans and `useState<string | null>(null)` for `selectedPlanId`. (b) `useEffect` fetches `GET /api/v1/plans` on mount. (c) Render plan selection cards above credential fields using `cn()` to show selected state (`border-primary bg-primary/5` when selected). Each card shows `plan.name` and `plan.monthly_request_limit.toLocaleString()`. (d) Add `plan_id: selectedPlanId` to POST body. (e) On success (201 or 200), read `data.checkout_url` and redirect with `window.location.href`. Remove `login()` + `navigate('/')`. (f) Disable submit button when `selectedPlanId === null`. (g) Add `Plan` interface: `{ id: string; name: string; monthly_request_limit: number }`.

- [x] **10.3** `frontend/src/pages/StripeSuccessPage.tsx` — Create. Public page at `/register/success`. Card layout matching the register page style (`bg-muted`, HookYard logo, Card component). Shows a success heading, confirmation message, and a `<Link to="/login">` button.

- [x] **10.4** `frontend/src/pages/StripeCancelPage.tsx` — Create. Public page at `/register/cancel`. Same layout. Shows a cancellation message and a `<Link to="/register">` button to try again.

- [x] **10.5** `frontend/src/pages/RegisterPendingPage.tsx` — Create. Public page at `/register/pending`. On mount, calls `POST /api/v1/register/resume` with the JWT token from `useAuth()`. Shows a spinner with "Resuming your checkout…". On success, sets `window.location.href = data.checkout_url`. On 409 (already active), calls `navigate('/')`. On 422 (no plan), calls `navigate('/register')`. On error, shows an Alert with a retry button.

- [x] **10.6** `frontend/src/App.tsx` — Add routes: `/register/success`, `/register/cancel`, `/register/pending` (all public, no `<ProtectedRoute>`). In the post-login redirect logic (wherever `login()` is called and then `navigate('/')` runs), check `user.status`: if `'pending_payment'`, call `navigate('/register/pending')` instead.
