<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\AuditRepositoryPort;
use App\Application\Value\AuditEntry;
use App\Application\Value\AuditFilters;
use App\Domain\AuditLog;
use App\Entity\AuditLog as AuditLogEntity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAuditRepository implements AuditRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findByUser(string $userId, AuditFilters $filters): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditLogEntity::class, 'a')
            ->where('a.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC');

        if (!empty($filters->actions)) {
            $qb->andWhere('a.action IN (:actions)')->setParameter('actions', $filters->actions);
        }

        if (!empty($filters->resources)) {
            $qb->andWhere('a.resource IN (:resources)')->setParameter('resources', $filters->resources);
        }

        if ($filters->dateFrom !== null) {
            $qb->andWhere('a.createdAt >= :dateFrom')->setParameter('dateFrom', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $qb->andWhere('a.createdAt <= :dateTo')->setParameter('dateTo', $filters->dateTo);
        }

        $qb->setFirstResult(($filters->page - 1) * $filters->perPage)
            ->setMaxResults($filters->perPage);

        /** @var AuditLogEntity[] $logs */
        $logs = $qb->getQuery()->getResult();

        return array_map(
            function (AuditLogEntity $log): AuditEntry {
                $domain = $log->toDomain();

                return new AuditEntry(
                    id:         $domain->getId(),
                    action:     $domain->getAction(),
                    resource:   $domain->getResource(),
                    resourceId: $domain->getResourceId(),
                    metadata:   $domain->getMetadata(),
                    createdAt:  $domain->getCreatedAt(),
                );
            },
            $logs,
        );
    }

    public function countByUser(string $userId, AuditFilters $filters): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(AuditLogEntity::class, 'a')
            ->where('a.user = :userId')
            ->setParameter('userId', $userId);

        if (!empty($filters->actions)) {
            $qb->andWhere('a.action IN (:actions)')->setParameter('actions', $filters->actions);
        }

        if (!empty($filters->resources)) {
            $qb->andWhere('a.resource IN (:resources)')->setParameter('resources', $filters->resources);
        }

        if ($filters->dateFrom !== null) {
            $qb->andWhere('a.createdAt >= :dateFrom')->setParameter('dateFrom', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $qb->andWhere('a.createdAt <= :dateTo')->setParameter('dateTo', $filters->dateTo);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(AuditLog $auditLog): void
    {
        /** @var User $user */
        $user   = $this->entityManager->getReference(User::class, $auditLog->getUserId());
        $entity = AuditLogEntity::fromDomain($auditLog, $user);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
