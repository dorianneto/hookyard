<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\ChangePlanUseCase;
use App\Domain\Exception\AlreadyOnPlanException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Domain\Plan;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ChangePlanUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $userRepository;
    private PlanRepositoryPort&MockObject $planRepository;
    private StripeServicePort&MockObject $stripeService;
    private ChangePlanUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryPort::class);
        $this->planRepository = $this->createMock(PlanRepositoryPort::class);
        $this->stripeService  = $this->createMock(StripeServicePort::class);
        $this->useCase        = new ChangePlanUseCase(
            $this->userRepository,
            $this->planRepository,
            $this->stripeService,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsAlreadyOnPlanWhenRequestedPlanMatchesCurrent(): void
    {
        $user = $this->makeUser(planId: 'plan_startup');

        $this->userRepository->method('findById')->willReturn($user);

        $this->expectException(AlreadyOnPlanException::class);

        $this->useCase->execute('req', 'user-id', 'plan_startup', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsPlanNotFoundWhenPlanDoesNotExist(): void
    {
        $user = $this->makeUser(planId: 'plan_startup');

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn(null);

        $this->expectException(PlanNotFoundException::class);

        $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsPlanNotConfiguredWhenNoPriceId(): void
    {
        $user = $this->makeUser(planId: 'plan_startup');
        $plan = new Plan('plan_pro', 'Pro', 100000, null, new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);

        $this->expectException(PlanNotConfiguredException::class);

        $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCancelsExistingSubscriptionBeforeCreatingCheckout(): void
    {
        $user = $this->makeUser(planId: 'plan_startup', stripeSubscriptionId: 'sub_existing');
        $plan = new Plan('plan_pro', 'Pro', 100000, 'price_pro', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/new');

        $this->stripeService
            ->expects($this->once())
            ->method('cancelSubscription')
            ->with('sub_existing');

        $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDoesNotCancelSubscriptionWhenUserHasNone(): void
    {
        $user = $this->makeUser(planId: 'plan_startup', stripeSubscriptionId: null);
        $plan = new Plan('plan_pro', 'Pro', 100000, 'price_pro', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/new');

        $this->stripeService
            ->expects($this->never())
            ->method('cancelSubscription');

        $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsCheckoutUrl(): void
    {
        $user = $this->makeUser(planId: 'plan_startup');
        $plan = new Plan('plan_pro', 'Pro', 100000, 'price_pro', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/cs_test');

        $url = $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');

        $this->assertSame('https://checkout.stripe.com/pay/cs_test', $url);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPassesExistingCustomerIdToCheckoutSession(): void
    {
        $user = $this->makeUser(planId: 'plan_startup');
        $plan = new Plan('plan_pro', 'Pro', 100000, 'price_pro', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);

        $this->stripeService
            ->expects($this->once())
            ->method('createCheckoutSession')
            ->with(
                'price_pro',
                'user-id',
                'plan_pro',
                'https://app/success',
                'https://app/cancel',
                'cus_test',
            )
            ->willReturn('https://checkout.stripe.com/pay/cs_test');

        $this->useCase->execute('req', 'user-id', 'plan_pro', 'https://app/success', 'https://app/cancel');
    }

    private function makeUser(string $planId = 'plan_startup', ?string $stripeSubscriptionId = null): User
    {
        return new User(
            id:                   'user-id',
            email:                'user@example.com',
            passwordHash:         'hash',
            createdAt:            new \DateTimeImmutable(),
            planId:               $planId,
            stripeCustomerId:     'cus_test',
            status:               'active',
            stripeSubscriptionId: $stripeSubscriptionId,
        );
    }
}
