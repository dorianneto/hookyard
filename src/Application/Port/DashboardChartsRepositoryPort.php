<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Value\DashboardCharts;

interface DashboardChartsRepositoryPort
{
    public function getChartsForUser(string $userId): DashboardCharts;
}
