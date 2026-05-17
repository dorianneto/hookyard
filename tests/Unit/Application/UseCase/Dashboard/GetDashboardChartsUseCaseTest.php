<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Dashboard;

use App\Application\Port\DashboardChartsRepositoryPort;
use App\Application\UseCase\Dashboard\GetDashboardChartsUseCase;
use App\Application\Value\DailyEventCount;
use App\Application\Value\DailyQuotaUsage;
use App\Application\Value\DashboardCharts;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GetDashboardChartsUseCaseTest extends TestCase
{
    private DashboardChartsRepositoryPort&MockObject $repository;
    private GetDashboardChartsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DashboardChartsRepositoryPort::class);
        $this->useCase    = new GetDashboardChartsUseCase($this->repository, new NullLogger());
    }

    public function testExecuteReturnsDashboardCharts(): void
    {
        $charts = new DashboardCharts(
            eventsByDay: [
                new DailyEventCount('2026-05-17', 42, 40, 1, 1),
            ],
            quotaByDay: [
                new DailyQuotaUsage('2026-05-17', 42),
            ],
        );

        $this->repository
            ->expects($this->once())
            ->method('getChartsForUser')
            ->with('user-id')
            ->willReturn($charts);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame($charts, $result);
    }

    public function testExecuteWithEmptyData(): void
    {
        $charts = new DashboardCharts(eventsByDay: [], quotaByDay: []);

        $this->repository
            ->expects($this->once())
            ->method('getChartsForUser')
            ->willReturn($charts);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame([], $result->eventsByDay);
        $this->assertSame([], $result->quotaByDay);
    }
}
