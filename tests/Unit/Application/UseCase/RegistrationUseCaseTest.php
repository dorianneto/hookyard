<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Domain\Plan;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RegistrationUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $repository;
    private PlanRepositoryPort&MockObject $planRepository;
    private StripeServicePort&MockObject $stripeService;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private RegisterUserUseCase $useCase;

    private Plan $plan;

    protected function setUp(): void
    {
        $this->repository      = $this->createMock(UserRepositoryPort::class);
        $this->planRepository  = $this->createMock(PlanRepositoryPort::class);
        $this->stripeService   = $this->createMock(StripeServicePort::class);
        $this->auditDispatcher = $this->createMock(AuditEventDispatcherPort::class);
        $this->useCase         = new RegisterUserUseCase(
            $this->repository,
            $this->planRepository,
            $this->stripeService,
            $this->auditDispatcher,
            new NullLogger(),
        );

        $this->plan = new Plan('plan-id', 'Starter', 1000, 'price_abc123', new \DateTimeImmutable());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsPlanNotFoundExceptionWhenPlanDoesNotExist(): void
    {
        $this->planRepository->method('findById')->willReturn(null);
        $this->repository->method('findByEmail')->willReturn(null);

        $this->expectException(PlanNotFoundException::class);

        $this->executeWithPlan('invalid-plan-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsPlanNotConfiguredExceptionWhenNoPriceId(): void
    {
        $unconfiguredPlan = new Plan('plan-id', 'Starter', 1000, null, new \DateTimeImmutable());
        $this->planRepository->method('findById')->willReturn($unconfiguredPlan);
        $this->repository->method('findByEmail')->willReturn(null);

        $this->expectException(PlanNotConfiguredException::class);

        $this->executeWithPlan('plan-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsEmailAlreadyTakenForActiveUser(): void
    {
        $activeUser = new User('existing-id', 'taken@example.com', 'hash', new \DateTimeImmutable(), status: 'active');

        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn($activeUser);

        $this->expectException(EmailAlreadyTakenException::class);

        $this->executeWithPlan('plan-id', 'taken@example.com');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsNewCheckoutUrlForPendingUserRetry(): void
    {
        $pendingUser = new User('existing-id', 'retry@example.com', 'hash', new \DateTimeImmutable(), planId: 'plan-id', status: 'pending_payment');

        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn($pendingUser);
        $this->repository->expects($this->never())->method('save');
        $this->auditDispatcher->expects($this->never())->method('dispatch');
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/retry');

        $url = $this->executeWithPlan('plan-id', 'retry@example.com');

        $this->assertSame('https://checkout.stripe.com/retry', $url);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavesNewUserWithPendingStatusOnSuccess(): void
    {
        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn(null);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/cs_new');

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getId() === 'test-id'
                    && $user->getEmail() === 'new@example.com'
                    && $user->getPlanId() === 'plan-id'
                    && $user->getStatus() === 'pending_payment';
            }));

        $this->executeWithPlan('plan-id', 'new@example.com', 'test-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsCheckoutUrlOnSuccess(): void
    {
        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn(null);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/cs_test');

        $url = $this->executeWithPlan('plan-id');

        $this->assertSame('https://checkout.stripe.com/pay/cs_test', $url);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDispatchesAuditEventAfterSuccessfulRegistration(): void
    {
        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn(null);
        $this->stripeService->method('createCheckoutSession')->willReturn('https://checkout.stripe.com/pay/cs_test');

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->action === 'create' && $event->resource === 'account';
            }));

        $this->executeWithPlan('plan-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCallsStripeWithCorrectArguments(): void
    {
        $this->planRepository->method('findById')->willReturn($this->plan);
        $this->repository->method('findByEmail')->willReturn(null);

        $this->stripeService
            ->expects($this->once())
            ->method('createCheckoutSession')
            ->with(
                'price_abc123',
                $this->anything(),
                'plan-id',
                'https://app/success',
                'https://app/cancel',
            )
            ->willReturn('https://checkout.stripe.com/pay/cs_test');

        $this->useCase->execute('request-id', 'test-id', 'new@example.com', 'hash', 'plan-id', 'https://app/success', 'https://app/cancel');
    }

    private function executeWithPlan(
        string $planId,
        string $email = 'new@example.com',
        string $id = 'test-id',
    ): string {
        return $this->useCase->execute('request-id', $id, $email, 'hashvalue', $planId, 'https://app/success', 'https://app/cancel');
    }
}
