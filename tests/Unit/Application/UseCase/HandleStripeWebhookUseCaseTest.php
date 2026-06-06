<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\HandleStripeWebhookUseCase;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class HandleStripeWebhookUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $userRepository;
    private HandleStripeWebhookUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryPort::class);
        $this->useCase        = new HandleStripeWebhookUseCase($this->userRepository, new NullLogger());
    }

    public function testExecuteActivatesUserWhenFound(): void
    {
        $user = new User(
            id:           'user-id',
            email:        'user@example.com',
            passwordHash: 'hash',
            createdAt:    new \DateTimeImmutable(),
            planId:       'plan-id',
            status:       'pending_payment',
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with('user-id')
            ->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $saved): bool {
                return $saved->getStatus() === 'active'
                    && $saved->getStripeCustomerId() === 'cus_test123';
            }));

        $this->useCase->execute('request-id', 'user-id', 'cus_test123');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteDoesNothingWhenUserNotFound(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->useCase->execute('request-id', 'missing-user-id', 'cus_test123');
    }
}
