<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\UpdateAccountUseCase;
use App\Domain\Exception\InvalidPasswordException;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UpdateAccountUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $repository;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private UpdateAccountUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository      = $this->createMock(UserRepositoryPort::class);
        $this->auditDispatcher = $this->createMock(AuditEventDispatcherPort::class);
        $this->useCase         = new UpdateAccountUseCase($this->repository, $this->auditDispatcher, new NullLogger());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteUpdatesNameOnly(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with('user-id')
            ->willReturn($existing);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getName() === 'Alice Updated'
                    && $user->getPasswordHash() === 'stored-hash';
            }));

        $result = $this->useCase->execute(
            requestId:            'req-1',
            userId:               'user-id',
            name:                 'Alice Updated',
            newPasswordHash:      null,
            currentPasswordPlain: null,
            passwordVerifier:     fn() => true,
        );

        $this->assertSame('Alice Updated', $result->getName());
        $this->assertSame('stored-hash', $result->getPasswordHash());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteUpdatesPasswordWhenCurrentPasswordIsCorrect(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($existing);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getPasswordHash() === 'new-hash';
            }));

        $verifier = function (string $plain, string $hash): bool {
            return $plain === 'correct-current' && $hash === 'stored-hash';
        };

        $result = $this->useCase->execute(
            requestId:            'req-2',
            userId:               'user-id',
            name:                 'Alice',
            newPasswordHash:      'new-hash',
            currentPasswordPlain: 'correct-current',
            passwordVerifier:     $verifier,
        );

        $this->assertSame('new-hash', $result->getPasswordHash());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenCurrentPasswordIsWrong(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($existing);

        $this->repository
            ->expects($this->never())
            ->method('save');

        $this->expectException(InvalidPasswordException::class);

        $this->useCase->execute(
            requestId:            'req-3',
            userId:               'user-id',
            name:                 'Alice',
            newPasswordHash:      'new-hash',
            currentPasswordPlain: 'wrong-password',
            passwordVerifier:     fn() => false,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChangedFieldsContainsNameWhenNameChanged(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');
        $this->repository->method('findById')->willReturn($existing);

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->metadata['changed_fields'] === ['name'];
            }));

        $this->useCase->execute('req', 'user-id', 'Alice Updated', null, null, fn() => true);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChangedFieldsContainsPasswordWhenPasswordChanged(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');
        $this->repository->method('findById')->willReturn($existing);

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->metadata['changed_fields'] === ['password'];
            }));

        $this->useCase->execute('req', 'user-id', 'Alice', 'new-hash', 'old-pass', fn() => true);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChangedFieldsContainsBothWhenNameAndPasswordChanged(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');
        $this->repository->method('findById')->willReturn($existing);

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->metadata['changed_fields'] === ['name', 'password'];
            }));

        $this->useCase->execute('req', 'user-id', 'Alice Updated', 'new-hash', 'old-pass', fn() => true);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChangedFieldsIsEmptyWhenNothingChanged(): void
    {
        $existing = new User('user-id', 'alice@example.com', 'stored-hash', new \DateTimeImmutable(), 'Alice');
        $this->repository->method('findById')->willReturn($existing);

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->metadata['changed_fields'] === [];
            }));

        $this->useCase->execute('req', 'user-id', 'Alice', null, null, fn() => true);
    }
}
