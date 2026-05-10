<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Source;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Source\CreateSourceUseCase;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CreateSourceUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $repository;
    private AuditEventDispatcherPort&MockObject $auditDispatcher;
    private CreateSourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository      = $this->createMock(SourceRepositoryPort::class);
        $this->auditDispatcher = $this->createMock(AuditEventDispatcherPort::class);
        $this->useCase         = new CreateSourceUseCase($this->repository, $this->auditDispatcher, new NullLogger());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteSavesSourceWithCorrectData(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Source $source): bool {
                return $source->getId() === 'test-id'
                    && $source->getUserId() === 'user-id'
                    && $source->getName() === 'My Source'
                    && $source->getInboundUuid() !== '';
            }));

        $result = $this->useCase->execute('request-id', 'test-id', 'user-id', 'My Source');

        $this->assertInstanceOf(Source::class, $result);
        $this->assertSame('test-id', $result->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteGeneratesUniqueInboundUuids(): void
    {
        $inboundUuids = [];
        $stub = $this->createStub(SourceRepositoryPort::class);
        $stub->method('save')->willReturnCallback(function (Source $source) use (&$inboundUuids): void {
            $inboundUuids[] = $source->getInboundUuid();
        });

        $dispatcherStub = $this->createStub(AuditEventDispatcherPort::class);
        $useCase = new CreateSourceUseCase($stub, $dispatcherStub, new NullLogger());
        $useCase->execute('request-id', 'id-1', 'user-id', 'Source 1');
        $useCase->execute('request-id', 'id-2', 'user-id', 'Source 2');

        $this->assertCount(2, array_unique($inboundUuids));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDispatchesAuditEventAfterSuccessfulSave(): void
    {
        $this->repository->method('save');

        $this->auditDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AuditableActionEvent $event): bool {
                return $event->action === 'create' && $event->resource === 'source';
            }));

        $this->useCase->execute('request-id', 'test-id', 'user-id', 'My Source');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDoesNotDispatchAuditEventWhenSaveThrows(): void
    {
        $this->repository
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->auditDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectException(\RuntimeException::class);

        $this->useCase->execute('request-id', 'test-id', 'user-id', 'My Source');
    }
}
