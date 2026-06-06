# PRD: Stripe Plan Registration

## Problem

The current registration flow creates accounts without any plan association — `planId` is always `null` after sign-up. There is no mechanism for users to choose a subscription tier or make a payment. Plans exist in the database but are never presented or linked during onboarding, making quota enforcement meaningless at account creation time.

## Goal

Add a plan-selection step to the registration flow and integrate Stripe Checkout so that every new user selects a plan and completes payment before accessing the platform. The account is created immediately on form submission; the plan association and Stripe customer ID are set once the `checkout.session.completed` webhook confirms payment. Users who close Stripe before paying must be able to resume payment without losing their account or being blocked from re-registering.

---

## Requirements

### Functional

- **FR-1**: A public `GET /api/v1/plans` endpoint returns all plans (id, name, monthly_request_limit). `stripe_price_id` is never exposed to clients.
- **FR-2**: `POST /api/v1/register` accepts a `plan_id` field in addition to existing fields. Missing or invalid `plan_id` returns 422.
- **FR-3**: On successful registration the backend creates a Stripe Checkout Session in `subscription` mode for the chosen plan's `stripe_price_id` and returns `{"checkout_url": "..."}` with HTTP 201.
- **FR-4**: The Stripe Checkout session carries `metadata.user_id` and `metadata.plan_id` so the webhook can correlate back to the account.
- **FR-5**: `POST /api/v1/stripe/webhook` verifies the `Stripe-Signature` header using the webhook secret, then handles `checkout.session.completed` by setting `planId`, `stripeCustomerId`, and `status = active` on the User.
- **FR-6**: The registration page shows plan cards fetched from the API. The user must select a plan before submitting the form.
- **FR-7**: After submission, the frontend redirects the browser to the Stripe Checkout URL.
- **FR-8**: `/register/success` shows a confirmation page with a link to log in. `/register/cancel` shows a cancellation page with a link back to the registration form.
- **FR-9**: `plans.stripe_price_id` is a nullable string column. The use case validates it is non-null before creating a checkout session and throws `PlanNotConfiguredException` if not.
- **FR-10**: Every `User` has a `status` field: `pending_payment` (created, payment not yet confirmed) or `active` (payment confirmed, plan linked). New users start as `pending_payment`; the webhook transitions them to `active`.
- **FR-11**: If `POST /api/v1/register` is called with an email that belongs to a `pending_payment` user, treat it as a retry — create a new Stripe Checkout session (with the newly selected plan) and return `{"checkout_url": "..."}` with HTTP 200. `EmailAlreadyTakenException` is only thrown for `active` users.
- **FR-12**: The `/me` endpoint includes `"status"` in the user payload so the frontend can detect pending users.
- **FR-13**: `POST /api/v1/register/resume` (authenticated) creates a fresh Stripe Checkout session for the currently authenticated `pending_payment` user and returns `{"checkout_url": "..."}`. Returns 409 if the user is already `active`.
- **FR-14**: After login, if the user's status is `pending_payment`, the frontend redirects to `/register/pending`. That page calls the resume endpoint and redirects the user to Stripe automatically.

### Non-functional

- **NFR-1**: The Stripe secret key and webhook signing secret come from environment variables (`STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`). They must never be logged or exposed in responses.
- **NFR-2**: The webhook endpoint is excluded from the Symfony security firewall (no JWT/session required) but protected by Stripe signature verification.
- **NFR-3**: All new use cases and controllers follow the `#[WithMonologChannel('hookyard')]` + structured context logging convention from CLAUDE.md.
- **NFR-4**: Domain and Application layers must have zero imports from `Stripe\` namespace.
- **NFR-5**: A `pending_payment` user can authenticate (valid credentials) but is immediately redirected to the payment resume flow; they cannot access any protected feature until status is `active`.

---

## API

### `GET /api/v1/plans`

Public (no authentication). Returns HTTP 200.

**Response:**

```json
{
  "data": [
    {
      "id": "01940000-0000-7000-8000-000000000001",
      "name": "Starter",
      "monthly_request_limit": 1000
    },
    {
      "id": "01940000-0000-7000-8000-000000000002",
      "name": "Pro",
      "monthly_request_limit": 10000
    }
  ]
}
```

---

### `POST /api/v1/register`

Public (no authentication). Returns HTTP 201 on success.

**Body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `email` | string | yes | Valid email |
| `password` | string | yes | Non-blank |
| `plan_id` | string | yes | Must match an existing plan |
| `name` | string | no | Display name |

**Response (201):**

```json
{
  "checkout_url": "https://checkout.stripe.com/pay/cs_test_a1b2c3..."
}
```

**Errors and retry behavior:**

| Status | Body | Condition |
|---|---|---|
| 422 | `{"error": "plan_id is required."}` | Missing plan_id |
| 422 | `{"error": "Plan not found."}` | plan_id doesn't exist |
| 422 | `{"error": "Email is already registered."}` | Email belongs to an `active` user |
| 200 | `{"checkout_url": "..."}` | Email belongs to a `pending_payment` user — returns a new checkout URL for the chosen plan |
| 500 | — | Stripe API error (logged as ERROR) |

---

### `POST /api/v1/register/resume`

Authenticated (JWT required). For users who are logged in but still `pending_payment`. Returns HTTP 200.

**Response (200):**

```json
{
  "checkout_url": "https://checkout.stripe.com/pay/cs_test_..."
}
```

**Errors:**

| Status | Body | Condition |
|---|---|---|
| 409 | `{"error": "Account is already active."}` | User status is already `active` |
| 500 | — | Stripe API error (logged as ERROR) |

---

### `POST /api/v1/stripe/webhook`

Unauthenticated. Stripe sends this. Returns HTTP 200 with `{}` on success, 400 on signature failure.

Handles event type `checkout.session.completed`. All other event types return 200 immediately (ignored).

---

## Implementation

### Backend

#### Domain: User — status field

**Modify** `src/Domain/User.php`

Add `private string $status = 'pending_payment'` as a constructor parameter and a `getStatus(): string` getter. Accepted values are `'pending_payment'` and `'active'`. No Symfony/Doctrine imports.

#### Domain Exception

**Create** `src/Domain/Exception/AccountAlreadyActiveException.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exception;
final class AccountAlreadyActiveException extends \RuntimeException {}
```

Thrown by `ResumeRegistrationUseCase` when the user is already `active`.

---

#### Domain: Plan

**Modify** `src/Domain/Plan.php`

Add `private ?string $stripePriceId` constructor parameter (nullable, defaults to `null`) with a `getStripePriceId(): ?string` getter. Position after `monthlyRequestLimit`.

#### Domain: User

**Modify** `src/Domain/User.php`

Add `private ?string $stripeCustomerId = null` constructor parameter with a `getStripeCustomerId(): ?string` getter.

#### Domain Exception

**Create** `src/Domain/Exception/PlanNotFoundException.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exception;
final class PlanNotFoundException extends \RuntimeException {}
```

**Create** `src/Domain/Exception/PlanNotConfiguredException.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exception;
final class PlanNotConfiguredException extends \RuntimeException {}
```

#### Entity: Plan

**Modify** `src/Entity/Plan.php`

Add:
```php
#[ORM\Column(name: 'stripe_price_id', type: Types::STRING, nullable: true)]
private ?string $stripePriceId = null;
```

Update constructor to accept `?string $stripePriceId = null` and update `toDomain()` to pass `$this->stripePriceId`.

#### Entity: User

**Modify** `src/Entity/User.php`

Add:
```php
#[ORM\Column(name: 'stripe_customer_id', type: Types::STRING, nullable: true)]
private ?string $stripeCustomerId = null;

#[ORM\Column(name: 'status', type: Types::STRING, length: 50, options: ['default' => 'pending_payment'])]
private string $status = 'pending_payment';
```

Update `fromDomain()` to set `$entity->stripeCustomerId = $domain->getStripeCustomerId()` and `$entity->status = $domain->getStatus()`. Update `toDomain()` to pass `stripeCustomerId: $this->stripeCustomerId` and `status: $this->status`.

#### Database Migration

**Create** via `php bin/console doctrine:migrations:diff`

Expected SQL:
```sql
ALTER TABLE plans ADD stripe_price_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD stripe_customer_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD status VARCHAR(50) NOT NULL DEFAULT 'pending_payment';
```

Note: existing users in the DB will get `status = 'pending_payment'` by default. If the environment has real users that pre-date Stripe, run a follow-up data migration: `UPDATE users SET status = 'active' WHERE plan_id IS NOT NULL`.

Commit the generated migration file alongside entity changes.

#### Port: PlanRepositoryPort

**Modify** `src/Application/Port/PlanRepositoryPort.php`

Add two methods:
```php
public function findById(string $id): ?Plan;
/** @return Plan[] */
public function findAll(): array;
```

#### Port: StripeServicePort

**Create** `src/Application/Port/StripeServicePort.php`

```php
<?php
declare(strict_types=1);
namespace App\Application\Port;

interface StripeServicePort
{
    /**
     * Creates a Stripe Checkout Session in subscription mode.
     * Returns the session URL to redirect the user to.
     */
    public function createCheckoutSession(
        string $stripePriceId,
        string $userId,
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string;
}
```

#### Infrastructure: DoctrinePlanRepository

**Modify** `src/Infrastructure/Persistence/DoctrinePlanRepository.php`

Implement `findById(string $id): ?Plan` and `findAll(): array`:

```php
public function findById(string $id): ?Plan
{
    $entity = $this->entityManager->find(PlanEntity::class, $id);
    return $entity?->toDomain();
}

public function findAll(): array
{
    return array_map(
        fn(PlanEntity $e) => $e->toDomain(),
        $this->entityManager->getRepository(PlanEntity::class)->findAll()
    );
}
```

#### Infrastructure: StripeService

**Create** `src/Infrastructure/Stripe/StripeService.php`

Implements `StripeServicePort`. Requires `stripe/stripe-php` Composer package.

```php
<?php
declare(strict_types=1);
namespace App\Infrastructure\Stripe;

use App\Application\Port\StripeServicePort;
use Stripe\StripeClient;

final class StripeService implements StripeServicePort
{
    private StripeClient $client;

    public function __construct(string $secretKey)
    {
        $this->client = new StripeClient($secretKey);
    }

    public function createCheckoutSession(
        string $stripePriceId,
        string $userId,
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $session = $this->client->checkout->sessions->create([
            'mode'        => 'subscription',
            'line_items'  => [['price' => $stripePriceId, 'quantity' => 1]],
            'metadata'    => ['user_id' => $userId, 'plan_id' => $planId],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
        ]);

        return $session->url;
    }
}
```

Register in `config/services.yaml`:
```yaml
App\Infrastructure\Stripe\StripeService:
    arguments:
        $secretKey: '%env(STRIPE_SECRET_KEY)%'
```

#### Use Case: ListPlansUseCase

**Create** `src/Application/UseCase/ListPlansUseCase.php`

```php
#[WithMonologChannel('hookyard')]
final class ListPlansUseCase
{
    public function __construct(
        private readonly PlanRepositoryPort $planRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return Plan[] */
    public function execute(string $requestId): array
    {
        $this->logger->info('List plans', ['request_id' => $requestId]);
        return $this->planRepository->findAll();
    }
}
```

#### Use Case: RegisterUserUseCase

**Modify** `src/Application/UseCase/RegisterUserUseCase.php`

- Add `PlanRepositoryPort $planRepository` and `StripeServicePort $stripeService` constructor parameters.
- Change `execute()` signature:
  ```php
  public function execute(
      string $requestId,
      string $id,
      string $email,
      string $passwordHash,
      string $planId,
      string $successUrl,
      string $cancelUrl,
      ?string $name = null,
  ): string
  ```
- Validate plan exists first (`findById($planId)`) — throw `PlanNotFoundException` if null; throw `PlanNotConfiguredException` if `stripe_price_id` is null.
- Then check email: if a user exists with that email AND `status === 'active'`, throw `EmailAlreadyTakenException`. If a user exists AND `status === 'pending_payment'`, skip user creation and reuse the existing user record (do not call `userRepository->save()` for a new user). If no user exists, create a new `User` with `status = 'pending_payment'` and `planId = null`, then save.
- Call `$this->stripeService->createCheckoutSession(...)` using the existing or newly-created user's ID and the validated plan.
- Dispatch audit event only for newly-created users (skip on retry).
- Return checkout URL string.
- Change return type to `string`.

#### Use Case: HandleStripeWebhookUseCase

**Create** `src/Application/UseCase/HandleStripeWebhookUseCase.php`

```php
#[WithMonologChannel('hookyard')]
final class HandleStripeWebhookUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $userId,
        string $planId,
        string $stripeCustomerId,
    ): void {
        $this->logger->info('Handle Stripe webhook', [
            'request_id'         => $requestId,
            'user_id'            => $userId,
            'plan_id'            => $planId,
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        $user = $this->userRepository->findById($userId);

        if (null === $user) {
            $this->logger->warning('Stripe webhook user not found', [
                'request_id' => $requestId,
                'user_id'    => $userId,
            ]);
            return;
        }

        $updatedUser = new User(
            id:               $user->getId(),
            email:            $user->getEmail(),
            passwordHash:     $user->getPasswordHash(),
            createdAt:        $user->getCreatedAt(),
            name:             $user->getName(),
            planId:           $planId,
            stripeCustomerId: $stripeCustomerId,
            status:           'active',           // transitions from pending_payment → active
        );

        $this->userRepository->save($updatedUser);

        $this->logger->info('Stripe webhook user activated', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);
    }
}
```

#### Use Case: ResumeRegistrationUseCase

**Create** `src/Application/UseCase/ResumeRegistrationUseCase.php`

For authenticated users in `pending_payment` status. Fetches the current user, validates status, creates a new Stripe Checkout session using their existing `planId` (if set) or a provided `planId`, and returns the URL.

Since the user may not have a `planId` yet (they abandoned before Stripe confirmed), the resume endpoint needs a plan selection too — or it can re-use the last plan from the most recent incomplete session. For simplicity: the resume endpoint re-uses whatever `planId` is stored on the user. If `planId` is null (user never completed a checkout), the frontend must redirect them back to `/register` to pick a plan. If `planId` is not null, a fresh checkout session is created.

```php
#[WithMonologChannel('hookyard')]
final class ResumeRegistrationUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly PlanRepositoryPort $planRepository,
        private readonly StripeServicePort $stripeService,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $userId,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $user = $this->userRepository->findById($userId);

        if ($user->getStatus() === 'active') {
            throw new AccountAlreadyActiveException('Account is already active.');
        }

        $plan = $this->planRepository->findById($user->getPlanId() ?? '');

        if (null === $plan || null === $plan->getStripePriceId()) {
            throw new PlanNotConfiguredException('No valid plan to resume checkout for.');
        }

        return $this->stripeService->createCheckoutSession(
            stripePriceId: $plan->getStripePriceId(),
            userId:        $userId,
            planId:        $plan->getId(),
            successUrl:    $successUrl,
            cancelUrl:     $cancelUrl,
        );
    }
}
```

> Note: because a new user's `planId` is `null` at registration (plan is set by webhook), the resume use case stores a `pendingPlanId` approach OR the `RegisterUserUseCase` optimistically sets `planId` on the user at registration (not just after webhook). Update the register use case to set `planId` immediately so resume can look it up. The webhook only changes `stripeCustomerId` and `status`; `planId` is already set.

**Update `RegisterUserUseCase`**: set `planId: $planId` on the `User` at creation time (not null). The webhook then only needs to update `stripeCustomerId` and `status = 'active'`. This is the cleaner model.

#### Controller: ResumeRegistrationController

**Create** `src/Controller/Api/v1/Stripe/ResumeRegistrationController.php`

```php
#[Route('/register/resume', name: 'api_v1_register_resume', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class ResumeRegistrationController
{
    public function __construct(
        private readonly ResumeRegistrationUseCase $resumeUseCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId  = $request->attributes->get('request_id');
        $user       = $request->attributes->get('user'); // injected by security layer
        $successUrl = $request->getSchemeAndHttpHost() . '/register/success';
        $cancelUrl  = $request->getSchemeAndHttpHost() . '/register/cancel';

        try {
            $checkoutUrl = $this->resumeUseCase->execute($requestId, $user->getId(), $successUrl, $cancelUrl);
        } catch (AccountAlreadyActiveException $e) {
            return new JsonResponse(['error' => 'Account is already active.'], Response::HTTP_CONFLICT);
        } catch (PlanNotConfiguredException $e) {
            $this->logger->error('Resume checkout: plan not configured', ['request_id' => $requestId]);
            return new JsonResponse(['error' => 'No plan to resume. Please register again.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['checkout_url' => $checkoutUrl]);
    }
}
```

This route is behind JWT authentication (protected). The user entity is retrieved from the security context — adapt the concrete access pattern to how the existing authenticated controllers read the current user.

#### Controller: MeController (modification)

**Modify** `src/Controller/MeController.php` (or the equivalent controller that returns the current user's data)

Add `"status": $user->getStatus()` to the JSON response. The frontend uses this field to detect `pending_payment` accounts after login.

#### Controller: ListPlansController

**Create** `src/Controller/Api/v1/Plan/ListPlansController.php`

```php
#[Route('/plans', name: 'api_v1_plans_list', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListPlansController
{
    public function __construct(
        private readonly ListPlansUseCase $listPlansUseCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $plans = $this->listPlansUseCase->execute($requestId);

        return new JsonResponse([
            'data' => array_map(fn(Plan $p) => [
                'id'                     => $p->getId(),
                'name'                   => $p->getName(),
                'monthly_request_limit'  => $p->getMonthlyRequestLimit(),
            ], $plans),
        ]);
    }
}
```

This route must be added to the firewall's public routes (no auth required).

#### Controller: RegistrationController

**Modify** `src/Controller/Api/v1/RegistrationController.php`

- Add validation for `plan_id` (NotBlank).
- Build `successUrl` and `cancelUrl` from `$request->getSchemeAndHttpHost()` + `/register/success` / `/register/cancel`.
- Pass `plan_id`, `successUrl`, `cancelUrl` to `registerUserUseCase->execute()`.
- Catch `PlanNotFoundException` → 422 `{"error": "Plan not found."}`.
- Catch `PlanNotConfiguredException` → 500 and log ERROR.
- Return `{"checkout_url": $checkoutUrl}` with HTTP 201 instead of `null`.

#### Controller: StripeWebhookController

**Create** `src/Controller/Api/v1/Stripe/WebhookController.php`

```php
#[Route('/stripe/webhook', name: 'api_v1_stripe_webhook', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class WebhookController
{
    public function __construct(
        private readonly HandleStripeWebhookUseCase $handleWebhookUseCase,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId  = $request->attributes->get('request_id');
        $payload    = $request->getContent();
        $sigHeader  = $request->headers->get('Stripe-Signature', '');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->warning('Stripe webhook signature invalid', ['request_id' => $requestId]);
            return new JsonResponse(['error' => 'Invalid signature.'], Response::HTTP_BAD_REQUEST);
        }

        if ($event->type !== 'checkout.session.completed') {
            return new JsonResponse([], Response::HTTP_OK);
        }

        $session = $event->data->object;
        $this->handleWebhookUseCase->execute(
            requestId:        $requestId,
            userId:           $session->metadata->user_id,
            planId:           $session->metadata->plan_id,
            stripeCustomerId: $session->customer,
        );

        return new JsonResponse([], Response::HTTP_OK);
    }
}
```

Register `$webhookSecret` in `services.yaml`:
```yaml
App\Controller\Api\v1\Stripe\WebhookController:
    arguments:
        $webhookSecret: '%env(STRIPE_WEBHOOK_SECRET)%'
```

#### Security Firewall

**Modify** `config/packages/security.yaml`

Add `/api/v1/plans` and `/api/v1/stripe/webhook` to the public access list (before the JWT-protected catch-all pattern).

#### Environment Variables

**Modify** `.env`

```dotenv
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Frontend

#### Plan Selection on RegisterPage

**Modify** `frontend/src/pages/RegisterPage.tsx`

- On mount, fetch `GET /api/v1/plans` and store plans in state.
- Render plan selection cards above the credential fields. Each card shows plan name and monthly_request_limit. Clicking a card sets `selectedPlanId`.
- Add `plan_id: selectedPlanId` to the POST body.
- On success (`201`), read `checkout_url` from the response body and redirect: `window.location.href = data.checkout_url`.
- Remove the `login()` + `navigate('/')` call — login happens after Stripe redirects back.
- Show a loading skeleton or spinner while plans are fetching.
- Disable the submit button if no plan is selected.

Plan card skeleton (Tailwind + shadcn):
```tsx
<div
  key={plan.id}
  onClick={() => setSelectedPlanId(plan.id)}
  className={cn(
    "cursor-pointer rounded-lg border p-4 transition-colors",
    selectedPlanId === plan.id
      ? "border-primary bg-primary/5"
      : "border-border hover:border-primary/50",
  )}
>
  <p className="font-semibold">{plan.name}</p>
  <p className="text-sm text-muted-foreground">
    {plan.monthly_request_limit.toLocaleString()} requests/month
  </p>
</div>
```

#### AuthContext — status field

**Modify** `frontend/src/contexts/AuthContext.tsx`

Add `status: 'pending_payment' | 'active'` to the `User` interface. The `/me` response now includes this field — map it when storing the user.

#### StripeSuccessPage

**Create** `frontend/src/pages/StripeSuccessPage.tsx`

Simple card page at `/register/success` telling the user their account is ready and linking to `/login`.

#### StripeCancelPage

**Create** `frontend/src/pages/StripeCancelPage.tsx`

Simple card page at `/register/cancel` telling the user they cancelled and linking back to `/register`.

#### RegisterPendingPage

**Create** `frontend/src/pages/RegisterPendingPage.tsx`

Public page at `/register/pending`. On mount it calls `POST /api/v1/register/resume` (with the JWT from auth context) and immediately redirects to the returned `checkout_url` via `window.location.href`. While fetching, show a spinner with "Resuming your checkout…". If the API returns 409 (already active), redirect to `/`. If the API returns 422 (no plan), redirect to `/register`.

#### Router

**Modify** `frontend/src/App.tsx` (or wherever routes are defined)

Add:
```tsx
<Route path="/register/success" element={<StripeSuccessPage />} />
<Route path="/register/cancel" element={<StripeCancelPage />} />
<Route path="/register/pending" element={<RegisterPendingPage />} />
```

All three routes are public (no `<ProtectedRoute>`). `RegisterPendingPage` reads the JWT from `localStorage`/context directly for the resume API call.

Add a redirect guard in the app's post-login flow: after `login()` resolves, check `user.status`. If `'pending_payment'`, call `navigate('/register/pending')` instead of `navigate('/')`.

---

## Files Summary

| Action | File |
|---|---|
| Modify | `src/Domain/Plan.php` |
| Modify | `src/Domain/User.php` |
| Create | `src/Domain/Exception/PlanNotFoundException.php` |
| Create | `src/Domain/Exception/PlanNotConfiguredException.php` |
| Create | `src/Domain/Exception/AccountAlreadyActiveException.php` |
| Modify | `src/Entity/Plan.php` |
| Modify | `src/Entity/User.php` |
| Create | `migrations/VersionXXXX.php` (generated) |
| Modify | `src/Application/Port/PlanRepositoryPort.php` |
| Create | `src/Application/Port/StripeServicePort.php` |
| Modify | `src/Infrastructure/Persistence/DoctrinePlanRepository.php` |
| Create | `src/Infrastructure/Stripe/StripeService.php` |
| Create | `src/Application/UseCase/ListPlansUseCase.php` |
| Modify | `src/Application/UseCase/RegisterUserUseCase.php` |
| Create | `src/Application/UseCase/HandleStripeWebhookUseCase.php` |
| Create | `src/Application/UseCase/ResumeRegistrationUseCase.php` |
| Create | `src/Controller/Api/v1/Plan/ListPlansController.php` |
| Modify | `src/Controller/Api/v1/RegistrationController.php` |
| Create | `src/Controller/Api/v1/Stripe/WebhookController.php` |
| Create | `src/Controller/Api/v1/Stripe/ResumeRegistrationController.php` |
| Modify | `src/Controller/MeController.php` |
| Modify | `config/packages/security.yaml` |
| Modify | `config/services.yaml` |
| Modify | `.env` |
| Modify | `frontend/src/pages/RegisterPage.tsx` |
| Create | `frontend/src/pages/StripeSuccessPage.tsx` |
| Create | `frontend/src/pages/StripeCancelPage.tsx` |
| Create | `frontend/src/pages/RegisterPendingPage.tsx` |
| Modify | `frontend/src/contexts/AuthContext.tsx` |
| Modify | `frontend/src/App.tsx` |

---

## Verification

| # | Check |
|---|---|
| 1 | `GET /api/v1/plans` returns 200 with a `data` array without `stripe_price_id` |
| 2 | `POST /api/v1/register` without `plan_id` returns 422 `{"error": "plan_id is required."}` |
| 3 | `POST /api/v1/register` with invalid `plan_id` returns 422 `{"error": "Plan not found."}` |
| 4 | `POST /api/v1/register` with valid fields returns 201 `{"checkout_url": "https://checkout.stripe.com/..."}` |
| 5 | A new `users` row is created with `plan_id = NULL` and `stripe_customer_id = NULL` |
| 6 | Simulating `checkout.session.completed` webhook sets `users.plan_id` and `users.stripe_customer_id` |
| 7 | Webhook with invalid `Stripe-Signature` returns 400 |
| 8 | Webhook with unknown event type returns 200 without modifying the DB |
| 9 | `php bin/phpunit tests/Unit/Application/UseCase/RegisterUserUseCaseTest.php` passes |
| 10 | `php bin/phpunit tests/Unit/Application/UseCase/HandleStripeWebhookUseCaseTest.php` passes |
| 11 | `php bin/phpunit tests/Unit/Application/UseCase/ListPlansUseCaseTest.php` passes |
| 12 | Registration page shows plan cards fetched from API |
| 13 | Submit button is disabled until a plan card is selected |
| 14 | After form submit, browser is redirected to Stripe Checkout URL |
| 15 | `/register/success` renders without errors and shows a login link |
| 16 | `/register/cancel` renders without errors and shows a link back to `/register` |
| 17 | `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` are not present in any log line |
| 18 | `php bin/phpunit` full suite passes with no regressions |
| 19 | Re-registering with a `pending_payment` email + a different plan returns 200 `{"checkout_url": "..."}` (not 422) |
| 20 | Re-registering with an `active` email returns 422 `{"error": "Email is already registered."}` |
| 21 | `users.status` is `pending_payment` immediately after registration and `active` after webhook fires |
| 22 | `GET /me` response includes `"status": "pending_payment"` for unpaid users |
| 23 | `POST /api/v1/register/resume` returns 200 `{"checkout_url": "..."}` for a `pending_payment` user |
| 24 | `POST /api/v1/register/resume` returns 409 for an `active` user |
| 25 | Logging in as a `pending_payment` user and visiting `/` redirects the browser to `/register/pending` |
| 26 | `/register/pending` page auto-fetches a checkout URL and redirects to Stripe |
| 27 | `php bin/phpunit tests/Unit/Application/UseCase/ResumeRegistrationUseCaseTest.php` passes |
