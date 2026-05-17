<?php

declare(strict_types=1);

namespace App\Application\UseCase\Dashboard;

use App\Application\Port\DashboardChartsRepositoryPort;
use App\Application\Value\DashboardCharts;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class GetDashboardChartsUseCase
{
    public function __construct(
        private readonly DashboardChartsRepositoryPort $chartsRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId): DashboardCharts
    {
        $this->logger->info('Get dashboard charts attempt', [
            'request_id' => $requestId,
        ]);

        $charts = $this->chartsRepository->getChartsForUser($userId);

        $this->logger->info('Get dashboard charts returned', [
            'request_id'    => $requestId,
            'events_by_day' => count($charts->eventsByDay),
            'quota_by_day'  => count($charts->quotaByDay),
        ]);

        return $charts;
    }
}
