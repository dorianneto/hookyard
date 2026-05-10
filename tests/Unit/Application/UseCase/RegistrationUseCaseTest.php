<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RegistrationUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $repository;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository      = $this->createMock(UserRepositoryPort::class);
        $this->auditDispatcher = $this->createMock(AuditEventDispatcherPort::class);
        $this->useCase         = new RegisterUserUseCase($this->repository, $this->auditDispatcher, new NullLogger());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenEmailAlreadyTaken(): void
    {
        $existing = new User('existing-id', 'taken@example.com', 'hash', new \DateTimeImmutable());

        $this->repository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($existing);

        $this->repository
            ->expects($this->never())
            ->method('save');

        $this->expectException(EmailAlreadyTakenException::class);

        $this->useCase->execute('request-id', 'new-id', 'taken@example.com', 'anyhash');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteSavesUserWhenEmailIsAvailable(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getId() === 'test-id'
                    && $user->getEmail() === 'new@example.com'
                    && $user->getPasswordHash() === 'hashvalue';
            }));

        $this->useCase->execute('request-id', 'test-id', 'new@example.com', 'hashvalue');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDispatchesAuditEventAfterSuccessfulRegistration(): void
    {
        $this->repository->method('findByEmail')->willReturn(null);

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->action === 'create' && $event->resource === 'account';
            }));

        $this->useCase->execute('request-id', 'test-id', 'new@example.com', 'hashvalue');
    }
}
