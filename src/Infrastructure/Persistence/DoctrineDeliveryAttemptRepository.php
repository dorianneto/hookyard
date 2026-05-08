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
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(da.id)')
            ->from(DeliveryAttemptEntity::class, 'da')
            ->where('da.event = :eventId')
            ->andWhere('da.endpoint = :endpointId')
            ->setParameter('eventId', $eventId)
            ->setParameter('endpointId', $endpointId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return DomainDeliveryAttempt[] */
    public function findAllByEventAndEndpoint(string $eventId, string $endpointId): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('da')
            ->from(DeliveryAttemptEntity::class, 'da')
            ->where('da.event = :eventId')
            ->andWhere('da.endpoint = :endpointId')
            ->setParameter('eventId', $eventId)
            ->setParameter('endpointId', $endpointId)
            ->orderBy('da.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn(DeliveryAttemptEntity $e) => $e->toDomain(), $entities);
    }
}
