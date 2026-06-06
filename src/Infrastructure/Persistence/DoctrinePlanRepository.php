<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan as DomainPlan;
use App\Entity\Plan as PlanEntity;
use App\Entity\User as UserEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePlanRepository implements PlanRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findByUserId(string $userId): ?DomainPlan
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PlanEntity::class, 'p')
            ->join(UserEntity::class, 'u', 'WITH', 'u.plan = p')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomain();
    }

    public function findById(string $id): ?DomainPlan
    {
        $entity = $this->entityManager->find(PlanEntity::class, $id);

        return $entity?->toDomain();
    }

    public function findAll(): array
    {
        return array_map(
            fn(PlanEntity $e) => $e->toDomain(),
            $this->entityManager->getRepository(PlanEntity::class)->findAll(),
        );
    }
}
