<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\LogRepositoryPort;
use App\Application\Value\LogEntry;
use App\Application\Value\LogFilters;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final class DoctrineLogRepository implements LogRepositoryPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findByUser(string $userId, LogFilters $filters): array
    {
        $latestCodeSub =
            '(SELECT da2.status_code FROM delivery_attempts da2'
            . ' WHERE da2.event_id = eed.event_id AND da2.endpoint_id = eed.endpoint_id'
            . ' ORDER BY da2.attempt_number DESC LIMIT 1)';

        $rows = $this->buildBaseQuery($userId, $filters)
            ->select(
                'e.id AS event_id',
                'e.received_at AS event_received_at',
                's.name AS source_name',
                's.id AS source_id',
                'ep.id AS endpoint_id',
                'ep.url AS endpoint_url',
                'eed.status AS delivery_status',
                'COALESCE(da_agg.attempt_count, 0) AS attempt_count',
                'da_agg.latest_attempt_at',
                $latestCodeSub . ' AS latest_attempt_status_code',
            )
            ->orderBy('e.received_at', 'DESC')
            ->setMaxResults($filters->perPage)
            ->setFirstResult(($filters->page - 1) * $filters->perPage)
            ->fetchAllAssociative();

        return array_map($this->toLogEntry(...), $rows);
    }

    public function countByUser(string $userId, LogFilters $filters): int
    {
        return (int) $this->buildBaseQuery($userId, $filters)
            ->select('COUNT(*)')
            ->fetchOne();
    }

    private function buildBaseQuery(string $userId, LogFilters $filters): QueryBuilder
    {
        $aggSub = $this->connection->createQueryBuilder()
            ->select('da.event_id, da.endpoint_id, COUNT(*) AS attempt_count, MAX(da.attempted_at) AS latest_attempt_at')
            ->from('delivery_attempts', 'da')
            ->groupBy('da.event_id, da.endpoint_id');

        $qb = $this->connection->createQueryBuilder()
            ->from('event_endpoint_deliveries', 'eed')
            ->join('eed', 'events',    'e',  'e.id  = eed.event_id')
            ->join('e',   'sources',   's',  's.id  = e.source_id')
            ->join('eed', 'endpoints', 'ep', 'ep.id = eed.endpoint_id')
            ->leftJoin('eed', '(' . $aggSub->getSQL() . ')', 'da_agg',
                'da_agg.event_id = eed.event_id AND da_agg.endpoint_id = eed.endpoint_id')
            ->where('s.user_id = :userId')
            ->setParameter('userId', $userId);

        if ($filters->endpointIds !== []) {
            $qb->andWhere('ep.id IN (:endpointIds)')
               ->setParameter('endpointIds', $filters->endpointIds, ArrayParameterType::STRING);
        }

        if ($filters->sourceIds !== []) {
            $qb->andWhere('s.id IN (:sourceIds)')
               ->setParameter('sourceIds', $filters->sourceIds, ArrayParameterType::STRING);
        }

        if ($filters->status !== null) {
            $qb->andWhere('eed.status = :status')
               ->setParameter('status', $filters->status);
        }

        return $qb;
    }

    private function toLogEntry(array $row): LogEntry
    {
        return new LogEntry(
            eventId:                  (string) $row['event_id'],
            eventReceivedAt:          new \DateTimeImmutable((string) $row['event_received_at']),
            sourceName:               (string) $row['source_name'],
            sourceId:                 (string) $row['source_id'],
            endpointId:               (string) $row['endpoint_id'],
            endpointUrl:              (string) $row['endpoint_url'],
            deliveryStatus:           (string) $row['delivery_status'],
            attemptCount:             (int) $row['attempt_count'],
            latestAttemptAt:          $row['latest_attempt_at'] !== null
                ? new \DateTimeImmutable((string) $row['latest_attempt_at'])
                : null,
            latestAttemptStatusCode:  $row['latest_attempt_status_code'] !== null
                ? (int) $row['latest_attempt_status_code']
                : null,
        );
    }
}
