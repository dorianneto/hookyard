<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\CancelSubscriptionUseCase;
use App\Domain\Exception\NoActiveSubscriptionException;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CancelSubscriptionUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $userRepository;
    private StripeServicePort&MockObject $stripeService;
    private CancelSubscriptionUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryPort::class);
        $this->stripeService  = $this->createMock(StripeServicePort::class);
        $this->useCase        = new CancelSubscriptionUseCase(
            $this->userRepository,
            $this->stripeService,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsNoActiveSubscriptionWhenUserHasNone(): void
    {
        $user = new User(
            id:                   'user-id',
            email:                'user@example.com',
            passwordHash:         'hash',
            createdAt:            new \DateTimeImmutable(),
            status:               'active',
            stripeSubscriptionId: null,
        );

        $this->userRepository->method('findById')->willReturn($user);

        $this->expectException(NoActiveSubscriptionException::class);

        $this->useCase->execute('req', 'user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsNoActiveSubscriptionWhenUserNotFound(): void
    {
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(NoActiveSubscriptionException::class);

        $this->useCase->execute('req', 'user-id');
    }

    public function testCancelsStripeSubscriptionAndSavesUser(): void
    {
        $user = new User(
            id:                   'user-id',
            email:                'user@example.com',
            passwordHash:         'hash',
            createdAt:            new \DateTimeImmutable(),
            planId:               'plan_startup',
            stripeCustomerId:     'cus_test',
            status:               'active',
            stripeSubscriptionId: 'sub_abc123',
        );

        $this->userRepository->method('findById')->willReturn($user);

        $this->stripeService
            ->expects($this->once())
            ->method('cancelSubscription')
            ->with('sub_abc123');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $saved): bool {
                return $saved->getStatus() === 'cancelled'
                    && $saved->getPlanId() === null
                    && $saved->getStripeSubscriptionId() === null;
            }));

        $this->useCase->execute('req', 'user-id');
    }
}
