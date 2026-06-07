<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan as DomainPlan;
use App\Entity\Plan as PlanEntity;
use App\Entity\User as UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityIdentityCollisionException;

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

    /**
     * @param DomainPlan[] $plans
     */
    public function bulkRemove(array $plans): void
    {
        foreach ($plans as $plan) {
            $entity = PlanEntity::fromDomain($plan);

            if ($this->entityManager->contains($entity) === false) {
                $entity = $this->entityManager->getRepository(PlanEntity::class)->findOneBy(['id' => $plan->getId()]);
            }

            $this->entityManager->remove($entity);
        }

        $this->entityManager->flush();
    }

    /**
     * @param DomainPlan[] $plans
     */
    public function bulkSave(array $plans): void
    {
        foreach ($plans as $plan) {
            $entity = PlanEntity::fromDomain($plan);

            try {
                $this->entityManager->persist($entity);
            } catch (EntityIdentityCollisionException $th) {
                $this->entityManager->detach($entity);
                $this->update($plan);
            }
        }

        $this->entityManager->flush();
    }

    public function update(DomainPlan $plan): void
    {
        $entity = PlanEntity::fromDomain($plan);

        if ($this->entityManager->contains($entity) === false) {
            $entity = $this->entityManager->getRepository(PlanEntity::class)->findOneBy(['id' => $plan->getId()]);
        }

        $entity->updateFromDomain($plan);

        $this->entityManager->flush();
    }
}
