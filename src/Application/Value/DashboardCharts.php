<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class DashboardCharts
{
    public function __construct(
        /** @var DailyEventCount[] */
        public array $eventsByDay,
        /** @var DailyQuotaUsage[] */
        public array $quotaByDay,
    ) {}
}
