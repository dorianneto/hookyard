<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\DashboardChartsRepositoryPort;
use App\Application\Value\DailyEventCount;
use App\Application\Value\DailyQuotaUsage;
use App\Application\Value\DashboardCharts;
use Doctrine\DBAL\Connection;

final class DoctrineDashboardChartsRepository implements DashboardChartsRepositoryPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function getChartsForUser(string $userId): DashboardCharts
    {
        $eventsRows = $this->connection->fetchAllAssociative(
            "SELECT DATE(e.received_at)                                         AS day,
                    COUNT(*)                                                    AS total,
                    COUNT(*) FILTER (WHERE e.status = 'delivered')              AS delivered,
                    COUNT(*) FILTER (WHERE e.status = 'pending')                AS pending,
                    COUNT(*) FILTER (WHERE e.status = 'failed')                 AS failed
             FROM events e
             JOIN sources s ON s.id = e.source_id
             WHERE s.user_id = :userId
               AND e.received_at >= CURRENT_DATE - INTERVAL '29 days'
             GROUP BY DATE(e.received_at)
             ORDER BY day ASC",
            ['userId' => $userId],
        );

        $quotaRows = $this->connection->fetchAllAssociative(
            "SELECT bucket_date::text AS day,
                    count
             FROM request_usage
             WHERE user_id = :userId
               AND bucket_date >= CURRENT_DATE - INTERVAL '29 days'
             ORDER BY bucket_date ASC",
            ['userId' => $userId],
        );

        $eventsByDay = array_map(
            fn(array $r) => new DailyEventCount(
                date:      (string) $r['day'],
                total:     (int) $r['total'],
                delivered: (int) $r['delivered'],
                pending:   (int) $r['pending'],
                failed:    (int) $r['failed'],
            ),
            $eventsRows,
        );

        $quotaByDay = array_map(
            fn(array $r) => new DailyQuotaUsage(
                date:  (string) $r['day'],
                count: (int) $r['count'],
            ),
            $quotaRows,
        );

        return new DashboardCharts($eventsByDay, $quotaByDay);
    }
}
