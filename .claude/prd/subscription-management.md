# PRD: Subscription Management

## Problem

Users currently have no way to view their active plan, upgrade or downgrade to a different tier, or cancel their subscription after the initial registration flow. The only plan-related surface is the registration wizard, making ongoing subscription management impossible without contacting support.

## Goal

Add a `/subscription` page accessible from the NavUser dropdown that lets authenticated active users see their current plan, switch to a different plan, and cancel their subscription. Plan changes reuse the existing Stripe Checkout flow; cancellation calls the Stripe Subscriptions API directly.

---

## Requirements

### Functional

- **FR-1**: The NavUser dropdown must expose a "Subscription" link navigating to `/subscription`.
- **FR-2**: `GET /api/v1/subscription` returns the user's current plan details and the full list of available plans.
- **FR-3**: `POST /api/v1/subscription/change-plan` accepts a `plan_id`, cancels any existing Stripe subscription, creates a new Stripe Checkout session for the target plan, and returns its URL.
- **FR-4**: `DELETE /api/v1/subscription` cancels the user's active Stripe subscription immediately and sets `status = 'cancelled'`.
- **FR-5**: The `checkout.session.completed` webhook must also persist `plan_id` and `stripe_subscription_id` extracted from the session, so subsequent plan changes and cancellations can operate on the correct subscription.
- **FR-6**: The subscription page highlights the current plan, disables the "Switch" button for it, and shows all other plans as selectable options.
- **FR-7**: The subscription page shows a destructive "Cancel Subscription" button that triggers a confirmation dialog before calling the cancel endpoint.
- **FR-8**: After a successful cancellation the frontend updates `AuthContext` status to `'cancelled'` and shows a cancelled-state message.

### Non-functional

- **NFR-1**: `DELETE /api/v1/subscription` must respond in under 3 seconds (Stripe cancel call is synchronous).
- **NFR-2**: All new use cases and controllers log through the `hookyard` Monolog channel with `request_id` in every log entry.
- **NFR-3**: No Symfony or Doctrine imports in `src/Domain/` or `src/Application/`.
- **NFR-4**: Unit tests cover all three new use cases with mocked ports and `new NullLogger()`.

---

## API

### `GET /api/v1/subscription`

Authenticated. Returns HTTP 200.

**Response:**

```json
{
  "current_plan": {
    "id": "plan_startup",
    "name": "Startup",
    "monthly_request_limit": 10000
  },
  "available_plans": [
    { "id": "plan_developer", "name": "Developer", "monthly_request_limit": 1000 },
    { "id": "plan_startup",   "name": "Startup",   "monthly_request_limit": 10000 },
    { "id": "plan_pro",       "name": "Pro",        "monthly_request_limit": 100000 }
  ],
  "status": "active"
}
```

`current_plan` is `null` if the user has no plan assigned.

---

### `POST /api/v1/subscription/change-plan`

Authenticated. Returns HTTP 200.

**Body:**

```json
{ "plan_id": "plan_pro" }
```

**Response (200):**

```json
{ "checkout_url": "https://checkout.stripe.com/c/pay/..." }
```

**Errors:**

| Status | Body | Condition |
|---|---|---|
| 400 | `{"error":"already_on_plan"}` | `plan_id` matches user's current plan |
| 404 | `{"error":"plan_not_found"}` | Plan does not exist |
| 422 | `{"error":"plan_not_configured"}` | Plan has no `stripe_price_id` |

---

### `DELETE /api/v1/subscription`

Authenticated. Returns HTTP 204 on success.

**Errors:**

| Status | Body | Condition |
|---|---|---|
| 422 | `{"error":"no_active_subscription"}` | User has no `stripe_subscription_id` or is not `active` |

---

## Implementation

### Backend

#### Domain — `src/Domain/User.php`

Add `stripeSubscriptionId` as a nullable constructor parameter alongside the existing Stripe fields:

```php
public function __construct(
    // ... existing params ...
    private ?string $stripeSubscriptionId = null,
) {}

public function getStripeSubscriptionId(): ?string
{
    return $this->stripeSubscriptionId;
}
```

#### Domain Exceptions — `src/Domain/Exception/`

**Create** `NoActiveSubscriptionException.php`:

```php
final class NoActiveSubscriptionException extends \RuntimeException {}
```

**Create** `AlreadyOnPlanException.php`:

```php
final class AlreadyOnPlanException extends \RuntimeException {}
```

#### Entity — `src/Entity/User.php`

Add ORM column:

```php
#[ORM\Column(length: 255, nullable: true)]
private ?string $stripeSubscriptionId = null;
```

Add getter/setter and wire it in `toDomain()` / `fromDomain()` / `updateFromDomain()` — mirror the pattern used for `stripeCustomerId`.

#### Database Migration

Run `php bin/console doctrine:migrations:diff` after modifying the entity. Commit the generated migration alongside the entity changes.

#### Port — `src/Application/Port/StripeServicePort.php`

Add method:

```php
public function cancelSubscription(string $subscriptionId): void;
```

#### Infrastructure — `src/Infrastructure/Stripe/StripeService.php`

Implement `cancelSubscription`:

```php
public function cancelSubscription(string $subscriptionId): void
{
    $this->client->subscriptions->cancel($subscriptionId);
}
```

#### Webhook — `src/Controller/Api/v1/Stripe/WebhookController.php`

Extract two additional fields from the completed session and forward them to the use case:

```php
$this->handleWebhookUseCase->execute(
    requestId:              $requestId,
    userId:                 $session->metadata->user_id,
    planId:                 $session->metadata->plan_id,
    stripeCustomerId:       $session->customer,
    stripeSubscriptionId:   $session->subscription,
);
```

#### Use Case — `src/Application/UseCase/HandleStripeWebhookUseCase.php`

Add `planId` and `stripeSubscriptionId` parameters to `execute()`. Build the updated `User` with all five mutable fields (`stripeCustomerId`, `stripeSubscriptionId`, `planId`, `status = 'active'`, and existing immutable fields).

#### Use Case — `src/Application/UseCase/GetSubscriptionUseCase.php` (new)

```php
#[WithMonologChannel('hookyard')]
final class GetSubscriptionUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly PlanRepositoryPort $planRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{current_plan: array|null, available_plans: array, status: string} */
    public function execute(string $requestId, string $userId): array
```

- Fetches the user; fetches all plans via `PlanRepositoryPort::findAll()`.
- Finds current plan by matching `$user->getPlanId()` against the list.
- Returns the structured array (no domain exceptions needed here).

#### Use Case — `src/Application/UseCase/ChangePlanUseCase.php` (new)

```php
#[WithMonologChannel('hookyard')]
final class ChangePlanUseCase
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
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string   // returns checkout URL
```

Logic:
1. Fetch user; if `$user->getPlanId() === $planId` throw `AlreadyOnPlanException`.
2. Fetch target plan via `PlanRepositoryPort::findById($planId)`; if null throw `PlanNotFoundException`.
3. If `$plan->getStripePriceId() === null` throw `PlanNotConfiguredException`.
4. If `$user->getStripeSubscriptionId() !== null`, call `$this->stripeService->cancelSubscription($user->getStripeSubscriptionId())`.
5. Call `$this->stripeService->createCheckoutSession(...)` and return the URL.
6. Log at INFO on completion.

#### Use Case — `src/Application/UseCase/CancelSubscriptionUseCase.php` (new)

```php
#[WithMonologChannel('hookyard')]
final class CancelSubscriptionUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly StripeServicePort $stripeService,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId): void
```

Logic:
1. Fetch user; if `$user->getStripeSubscriptionId() === null` throw `NoActiveSubscriptionException`.
2. Call `$this->stripeService->cancelSubscription($user->getStripeSubscriptionId())`.
3. Build updated `User` with `status = 'cancelled'`, `planId = null`, `stripeSubscriptionId = null`.
4. Save user via `$this->userRepository->save(...)`.
5. Log at INFO on completion.

#### Controller — `src/Controller/Api/v1/Subscription/GetSubscriptionController.php` (new)

```php
#[Route('/api/v1/subscription', name: 'api_v1_subscription_get', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class GetSubscriptionController
```

- Reads `$request->attributes->get('request_id')` and the Symfony Security user.
- Calls `GetSubscriptionUseCase::execute($requestId, $userId)`.
- Returns `JsonResponse` with the result array, HTTP 200.

#### Controller — `src/Controller/Api/v1/Subscription/ChangePlanController.php` (new)

```php
#[Route('/api/v1/subscription/change-plan', name: 'api_v1_subscription_change_plan', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class ChangePlanController
```

- Decodes JSON body; extracts `plan_id`.
- Constructs `successUrl = $request->getSchemeAndHttpHost() . '/subscription?changed=1'` and `cancelUrl = $request->getSchemeAndHttpHost() . '/subscription'`.
- Calls `ChangePlanUseCase::execute(...)`.
- Catches `AlreadyOnPlanException` → 400 `{"error":"already_on_plan"}`.
- Catches `PlanNotFoundException` → 404 `{"error":"plan_not_found"}`.
- Catches `PlanNotConfiguredException` → 422 `{"error":"plan_not_configured"}`.
- Returns 200 `{"checkout_url": "..."}` on success.

#### Controller — `src/Controller/Api/v1/Subscription/CancelSubscriptionController.php` (new)

```php
#[Route('/api/v1/subscription', name: 'api_v1_subscription_cancel', methods: ['DELETE'])]
#[WithMonologChannel('hookyard')]
final class CancelSubscriptionController
```

- Calls `CancelSubscriptionUseCase::execute(...)`.
- Catches `NoActiveSubscriptionException` → 422 `{"error":"no_active_subscription"}`.
- Returns 204 on success.

#### Unit Tests

**Create** `tests/Application/UseCase/GetSubscriptionUseCaseTest.php`
**Create** `tests/Application/UseCase/ChangePlanUseCaseTest.php`
**Create** `tests/Application/UseCase/CancelSubscriptionUseCaseTest.php`

Each test class:
- Creates mocks for all ports via `createMock()`.
- Passes `new NullLogger()` for `LoggerInterface`.
- Covers: happy path, exception paths (e.g., user not found, already on plan, no active subscription).

### Frontend

#### `frontend/src/contexts/AuthContext.tsx`

Extend the `User` type with the `'cancelled'` status:

```typescript
status: 'pending_payment' | 'active' | 'cancelled'
```

#### `frontend/src/components/NavUser.tsx`

Add a "Subscription" `DropdownMenuItem` with `IconCreditCard` between "Account" and "Audit":

```tsx
import { IconCreditCard, ... } from "@tabler/icons-react";

<DropdownMenuItem asChild>
  <Link to="/subscription">
    <IconCreditCard />
    Subscription
  </Link>
</DropdownMenuItem>
```

#### `frontend/src/pages/SubscriptionPage.tsx` (new)

State: `subscription` (API response), `loading`, `error`, `switching` (plan ID being switched to), `cancelling`.

Behaviour:
- On mount: `fetch('/api/v1/subscription')` → store result.
- If `subscription.status === 'cancelled'` render a full-page cancelled-state card with a message and no action buttons.
- Otherwise render:
  - A card showing current plan name + monthly limit.
  - A plan comparison grid — one card per available plan. Current plan card shows a "Current Plan" badge and disabled button. Other cards show a "Switch to [name]" button.
  - A "Cancel Subscription" `Button variant="destructive"` at the bottom that opens an `AlertDialog` for confirmation.

Plan switch flow:
```typescript
const handleSwitch = async (planId: string) => {
  setSwitching(planId)
  const res = await fetch('/api/v1/subscription/change-plan', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ plan_id: planId }),
  })
  const { checkout_url } = await res.json()
  window.location.href = checkout_url
}
```

Cancel flow:
```typescript
const handleCancel = async () => {
  setCancelling(true)
  await fetch('/api/v1/subscription', { method: 'DELETE' })
  updateUser({ ...user!, status: 'cancelled' })
  toast.success('Subscription cancelled.')
  // re-fetch to reflect cancelled state
  setSubscription(prev => ({ ...prev!, status: 'cancelled' }))
}
```

`?changed=1` query param: on mount if present, show a Sonner toast "Plan updated successfully."

Wrap the page in `<Layout>` with a `<Breadcrumb>` showing Home → Subscription.

#### `frontend/src/App.tsx`

Import `SubscriptionPage` and add a protected route:

```tsx
<Route path="/subscription" element={<ProtectedRoute><SubscriptionPage /></ProtectedRoute>} />
```

---

## Files Summary

| Action | File |
|---|---|
| Modify | `src/Domain/User.php` |
| Create | `src/Domain/Exception/NoActiveSubscriptionException.php` |
| Create | `src/Domain/Exception/AlreadyOnPlanException.php` |
| Modify | `src/Entity/User.php` |
| Create | `migrations/VersionXXXX.php` (auto-generated) |
| Modify | `src/Application/Port/StripeServicePort.php` |
| Modify | `src/Infrastructure/Stripe/StripeService.php` |
| Modify | `src/Controller/Api/v1/Stripe/WebhookController.php` |
| Modify | `src/Application/UseCase/HandleStripeWebhookUseCase.php` |
| Create | `src/Application/UseCase/GetSubscriptionUseCase.php` |
| Create | `src/Application/UseCase/ChangePlanUseCase.php` |
| Create | `src/Application/UseCase/CancelSubscriptionUseCase.php` |
| Create | `src/Controller/Api/v1/Subscription/GetSubscriptionController.php` |
| Create | `src/Controller/Api/v1/Subscription/ChangePlanController.php` |
| Create | `src/Controller/Api/v1/Subscription/CancelSubscriptionController.php` |
| Create | `tests/Application/UseCase/GetSubscriptionUseCaseTest.php` |
| Create | `tests/Application/UseCase/ChangePlanUseCaseTest.php` |
| Create | `tests/Application/UseCase/CancelSubscriptionUseCaseTest.php` |
| Modify | `frontend/src/contexts/AuthContext.tsx` |
| Modify | `frontend/src/components/NavUser.tsx` |
| Create | `frontend/src/pages/SubscriptionPage.tsx` |
| Modify | `frontend/src/App.tsx` |

---

## Verification

| # | Check |
|---|---|
| 1 | `GET /api/v1/subscription` returns 200 with `current_plan`, `available_plans`, `status` |
| 2 | `GET /api/v1/subscription` with unauthenticated request returns 401 |
| 3 | `POST /api/v1/subscription/change-plan` with the user's current `plan_id` returns 400 `already_on_plan` |
| 4 | `POST /api/v1/subscription/change-plan` with a non-existent plan returns 404 `plan_not_found` |
| 5 | `POST /api/v1/subscription/change-plan` with a valid different plan returns 200 `checkout_url` |
| 6 | After plan-change checkout completes, webhook updates user's `plan_id` and `stripe_subscription_id` in DB |
| 7 | `DELETE /api/v1/subscription` on a user with no `stripe_subscription_id` returns 422 `no_active_subscription` |
| 8 | `DELETE /api/v1/subscription` on an active user returns 204 and sets `status = 'cancelled'` in DB |
| 9 | `php bin/phpunit tests/Application/UseCase/GetSubscriptionUseCaseTest.php` passes |
| 10 | `php bin/phpunit tests/Application/UseCase/ChangePlanUseCaseTest.php` passes |
| 11 | `php bin/phpunit tests/Application/UseCase/CancelSubscriptionUseCaseTest.php` passes |
| 12 | NavUser dropdown shows "Subscription" item with credit card icon between Account and Audit |
| 13 | Clicking "Subscription" in NavUser navigates to `/subscription` |
| 14 | `/subscription` page shows current plan highlighted with a "Current Plan" badge |
| 15 | Other plan cards show enabled "Switch to [name]" buttons |
| 16 | Clicking "Switch to [name]" POSTs to `/api/v1/subscription/change-plan` and redirects to Stripe |
| 17 | Returning to `/subscription?changed=1` shows "Plan updated successfully" toast |
| 18 | Clicking "Cancel Subscription" opens a confirmation dialog |
| 19 | Confirming cancellation DELETEs `/api/v1/subscription` and renders cancelled-state UI |
| 20 | Visiting `/subscription` while `status === 'cancelled'` renders cancelled-state card without action buttons |
