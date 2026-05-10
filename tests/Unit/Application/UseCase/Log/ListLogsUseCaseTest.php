<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Log;

use App\Application\Port\LogRepositoryPort;
use App\Application\UseCase\Log\ListLogsUseCase;
use App\Application\Value\LogEntry;
use App\Application\Value\LogFilters;
use App\Application\Value\LogsResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListLogsUseCaseTest extends TestCase
{
    private LogRepositoryPort&MockObject $logRepository;
    private ListLogsUseCase $useCase;

    protected function setUp(): void
    {
        $this->logRepository = $this->createMock(LogRepositoryPort::class);
        $this->useCase       = new ListLogsUseCase($this->logRepository, new NullLogger());
    }

    public function testExecuteReturnsLogsResult(): void
    {
        $entry = new LogEntry(
            eventId:                 'event-1',
            eventReceivedAt:         new \DateTimeImmutable('2026-05-01T12:00:00+00:00'),
            sourceName:              'My Source',
            sourceId:                'source-1',
            endpointId:              'endpoint-1',
            endpointUrl:             'https://example.com/webhook',
            deliveryStatus:          'delivered',
            attemptCount:            1,
            latestAttemptAt:         new \DateTimeImmutable('2026-05-01T12:00:01+00:00'),
            latestAttemptStatusCode: 200,
        );

        $filters = new LogFilters();

        $this->logRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with('user-id', $filters)
            ->willReturn([$entry]);

        $this->logRepository
            ->expects($this->once())
            ->method('countByUser')
            ->with('user-id', $filters)
            ->willReturn(1);

        $result = $this->useCase->execute('request-id', 'user-id', $filters);

        $this->assertInstanceOf(LogsResult::class, $result);
        $this->assertSame([$entry], $result->entries);
        $this->assertSame(1, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(20, $result->perPage);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteWithNoFilters(): void
    {
        $filters = new LogFilters();

        $this->logRepository->method('findByUser')->willReturn([]);
        $this->logRepository->method('countByUser')->willReturn(0);

        $result = $this->useCase->execute('request-id', 'user-id', $filters);

        $this->assertSame([], $result->entries);
        $this->assertSame(0, $result->total);
    }

    public function testExecuteWithEndpointFilter(): void
    {
        $filters = new LogFilters(endpointIds: ['endpoint-1', 'endpoint-2']);

        $this->logRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with('user-id', $filters)
            ->willReturn([]);

        $this->logRepository
            ->expects($this->once())
            ->method('countByUser')
            ->with('user-id', $filters)
            ->willReturn(0);

        $this->useCase->execute('request-id', 'user-id', $filters);
    }

    public function testExecuteWithStatusFilter(): void
    {
        $filters = new LogFilters(status: 'failed');

        $this->logRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with('user-id', $filters)
            ->willReturn([]);

        $this->logRepository
            ->expects($this->once())
            ->method('countByUser')
            ->with('user-id', $filters)
            ->willReturn(0);

        $this->useCase->execute('request-id', 'user-id', $filters);
    }
}
