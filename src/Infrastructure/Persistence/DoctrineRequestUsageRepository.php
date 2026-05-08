<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\RequestUsageRepositoryPort;
use App\Entity\RequestUsage as RequestUsageEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRequestUsageRepository implements RequestUsageRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {}

    public function sumRolling30Days(string $userId): int
    {
        $cutoff = new DateTimeImmutable('-29 days');

        $result = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(ru.count), 0)')
            ->from(RequestUsageEntity::class, 'ru')
            ->where('ru.user = :userId')
            ->andWhere('ru.bucketDate >= :cutoff')
            ->setParameter('userId', $userId)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function incrementToday(string $userId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO request_usage (user_id, bucket_date, count)
             VALUES (:userId, CURRENT_DATE, 1)
             ON CONFLICT (user_id, bucket_date) DO UPDATE SET count = request_usage.count + 1',
            ['userId' => $userId],
        );
    }

    public function deleteOlderThan(DateTimeImmutable $before): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(RequestUsageEntity::class, 'ru')
            ->where('ru.bucketDate < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
