<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Audit;

use App\Application\Port\AuditRepositoryPort;
use App\Application\UseCase\Audit\ListAuditLogsUseCase;
use App\Application\Value\AuditEntry;
use App\Application\Value\AuditFilters;
use App\Application\Value\AuditResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListAuditLogsUseCaseTest extends TestCase
{
    private AuditRepositoryPort&MockObject $repository;
    private ListAuditLogsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditRepositoryPort::class);
        $this->useCase    = new ListAuditLogsUseCase($this->repository, new NullLogger());
    }

    public function testExecuteCallsRepositoryWithFiltersAndReturnsResult(): void
    {
        $filters = new AuditFilters(actions: ['create'], page: 2, perPage: 10);
        $entry   = new AuditEntry('id-1', 'create', 'source', null, [], new \DateTimeImmutable());

        $this->repository
            ->expects($this->once())
            ->method('findByUser')
            ->with('user-id', $filters)
            ->willReturn([$entry]);

        $this->repository
            ->expects($this->once())
            ->method('countByUser')
            ->with('user-id', $filters)
            ->willReturn(42);

        $result = $this->useCase->execute('req-1', 'user-id', $filters);

        $this->assertInstanceOf(AuditResult::class, $result);
        $this->assertSame([$entry], $result->entries);
        $this->assertSame(42, $result->total);
        $this->assertSame(2, $result->page);
        $this->assertSame(10, $result->perPage);
    }
}
