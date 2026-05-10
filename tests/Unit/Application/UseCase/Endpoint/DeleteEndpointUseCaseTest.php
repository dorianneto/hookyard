<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\Port\TransactionPort;
use App\Application\UseCase\Endpoint\DeleteEndpointUseCase;
use App\Domain\Endpoint;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DeleteEndpointUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private EventRepositoryPort&MockObject $eventRepository;
    private TransactionPort&Stub $transaction;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private DeleteEndpointUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(EndpointRepositoryPort::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryPort::class);
        $this->eventRepository  = $this->createMock(EventRepositoryPort::class);
        $this->transaction      = $this->createStub(TransactionPort::class);
        $this->auditDispatcher  = $this->createMock(AuditEventDispatcherPort::class);

        $this->transaction
            ->method('execute')
            ->willReturnCallback(static fn(callable $op) => $op());

        $this->useCase = new DeleteEndpointUseCase(
            $this->repository,
            $this->sourceRepository,
            $this->eventRepository,
            $this->transaction,
            $this->auditDispatcher,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteDeletesEventsBeforeEndpoint(): void
    {
        $endpoint = new Endpoint('endpoint-id', 'source-id', 'https://example.com', new \DateTimeImmutable());

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with('endpoint-id')
            ->willReturn($endpoint);

        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $callOrder = [];

        $this->eventRepository
            ->expects($this->once())
            ->method('deleteByEndpointId')
            ->with('endpoint-id')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'deleteByEndpointId';
            });

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('endpoint-id')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'delete';
            });

        $this->useCase->execute('request-id', 'endpoint-id', 'user-id');

        $this->assertSame(['deleteByEndpointId', 'delete'], $callOrder);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenEndpointNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(EndpointNotFoundException::class);

        $this->useCase->execute('request-id', 'missing-id', 'user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenSourceNotOwned(): void
    {
        $endpoint = new Endpoint('endpoint-id', 'source-id', 'https://example.com', new \DateTimeImmutable());

        $this->repository
            ->method('findById')
            ->willReturn($endpoint);

        $this->sourceRepository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('request-id', 'endpoint-id', 'other-user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDispatchesAuditEventAfterSuccessfulTransaction(): void
    {
        $endpoint = new Endpoint('endpoint-id', 'source-id', 'https://example.com', new \DateTimeImmutable());

        $this->repository
            ->method('findById')
            ->willReturn($endpoint);

        $this->sourceRepository
            ->method('findById')
            ->willReturn($this->createStub(Source::class));

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->action === 'delete' && $event->resource === 'endpoint';
            }));

        $this->useCase->execute('request-id', 'endpoint-id', 'user-id');
    }
}
