<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Source;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Source\DeleteSourceUseCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DeleteSourceUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $repository;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private DeleteSourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository      = $this->createMock(SourceRepositoryPort::class);
        $this->auditDispatcher = $this->createMock(AuditEventDispatcherPort::class);
        $this->useCase         = new DeleteSourceUseCase($this->repository, $this->auditDispatcher, new NullLogger());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteCallsDeleteWithCorrectArguments(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('source-id', 'user-id');

        $this->useCase->execute('request-id', 'source-id', 'user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDispatchesAuditEventAfterSuccessfulDelete(): void
    {
        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->action === 'delete' && $event->resource === 'source';
            }));

        $this->useCase->execute('request-id', 'source-id', 'user-id');
    }
}
