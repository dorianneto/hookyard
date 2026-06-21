<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\ResumeRegistrationUseCase;
use App\Domain\Exception\AccountAlreadyActiveException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Domain\Plan;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ResumeRegistrationUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $userRepository;
    private PlanRepositoryPort&MockObject $planRepository;
    private StripeServicePort&MockObject $stripeService;
    private ResumeRegistrationUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryPort::class);
        $this->planRepository = $this->createMock(PlanRepositoryPort::class);
        $this->stripeService  = $this->createMock(StripeServicePort::class);
        $this->useCase        = new ResumeRegistrationUseCase(
            $this->userRepository,
            $this->planRepository,
            $this->stripeService,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsCheckoutUrlForPendingUser(): void
    {
        $user = new User('user-id', 'u@example.com', 'hash', new \DateTimeImmutable(), planId: 'plan-id', status: 'pending_payment');
        $plan = new Plan('plan-id', 'Starter', 1000, 'price_abc123', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/cs_test');

        $url = $this->useCase->execute('request-id', 'user-id', 'https://app/success', 'https://app/cancel');

        $this->assertSame('https://checkout.stripe.com/pay/cs_test', $url);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenUserIsAlreadyActive(): void
    {
        $user = new User('user-id', 'u@example.com', 'hash', new \DateTimeImmutable(), status: 'active');
        $this->userRepository->method('findById')->willReturn($user);

        $this->expectException(AccountAlreadyActiveException::class);

        $this->useCase->execute('request-id', 'user-id', 'https://app/success', 'https://app/cancel');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenPlanHasNoStripePriceId(): void
    {
        $user = new User('user-id', 'u@example.com', 'hash', new \DateTimeImmutable(), planId: 'plan-id', status: 'pending_payment');
        $plan = new Plan('plan-id', 'Starter', 1000, null, new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findById')->willReturn($plan);

        $this->expectException(PlanNotConfiguredException::class);

        $this->useCase->execute('request-id', 'user-id', 'https://app/success', 'https://app/cancel');
    }
}
