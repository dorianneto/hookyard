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
        $existing = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(UserEntity::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $user->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof UserEntity) {
            $existing->setName($user->getName());
            $existing->setPasswordHash($user->getPasswordHash());
            $existing->setStripeCustomerId($user->getStripeCustomerId());
            $existing->setStatus($user->getStatus());

            if ($user->getPlanId() !== null) {
                $existing->setPlan($this->entityManager->getReference(PlanEntity::class, $user->getPlanId()));
            }
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
        $entity = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(UserEntity::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomain();
    }

    public function findById(string $id): ?DomainUser
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(UserEntity::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomain();
    }
}
