<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Domain\DeliveryAttempt as DomainDeliveryAttempt;
use App\Entity\DeliveryAttempt as DeliveryAttemptEntity;
use App\Entity\Endpoint as EndpointEntity;
use App\Entity\Event as EventEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDeliveryAttemptRepository implements DeliveryAttemptRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainDeliveryAttempt $attempt): void
    {
        $event = $this->entityManager->getReference(EventEntity::class, $attempt->getEventId());
        $endpoint = $this->entityManager->getReference(EndpointEntity::class, $attempt->getEndpointId());
        $entity = DeliveryAttemptEntity::fromDomain($attempt, $event, $endpoint);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function countByEventAndEndpoint(string $eventId, string $endpointId): int
    {
        return count(
            $this->entityManager
                ->getRepository(DeliveryAttemptEntity::class)
                ->findBy(['event' => $eventId, 'endpoint' => $endpointId])
        );
    }

    /** @return DomainDeliveryAttempt[] */
    public function findAllByEventAndEndpoint(string $eventId, string $endpointId): array
    {
        $entities = $this->entityManager
            ->getRepository(DeliveryAttemptEntity::class)
            ->findBy(['event' => $eventId, 'endpoint' => $endpointId], ['attemptNumber' => 'ASC']);

        return array_map(static fn(DeliveryAttemptEntity $e) => $e->toDomain(), $entities);
    }
}
