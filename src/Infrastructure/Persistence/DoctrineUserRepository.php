<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\UserRepositoryPort;
use App\Domain\User as DomainUser;
use App\Entity\Plan as PlanEntity;
use App\Entity\User as UserEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainUser $user): void
    {
        $existing = $this->entityManager->getRepository(UserEntity::class)->find($user->getId());

        if ($existing instanceof UserEntity) {
            $existing->setName($user->getName());
            $existing->setPasswordHash($user->getPasswordHash());
        } else {
            $plan = $user->getPlanId() !== null
                ? $this->entityManager->getReference(PlanEntity::class, $user->getPlanId())
                : null;
            $entity = UserEntity::fromDomain($user, $plan);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findByEmail(string $email): ?DomainUser
    {
        $entity = $this->entityManager
            ->getRepository(UserEntity::class)
            ->findOneBy(['email' => $email]);

        return $entity?->toDomain();
    }

    public function findById(string $id): ?DomainUser
    {
        $entity = $this->entityManager->getRepository(UserEntity::class)->find($id);

        return $entity?->toDomain();
    }
}
