<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EventRepositoryPort;
use App\Domain\Event as DomainEvent;
use App\Domain\EventStatus;
use App\Entity\Event as EventEntity;
use App\Entity\EventEndpointDelivery as EventEndpointDeliveryEntity;
use App\Entity\Source as SourceEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEventRepository implements EventRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainEvent $event): void
    {
        $source = $this->entityManager->getReference(SourceEntity::class, $event->getSourceId());
        $entity = EventEntity::fromDomain($event, $source);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?DomainEvent
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(EventEntity::class, 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity?->toDomain();
    }

    /** @return DomainEvent[] */
    public function findRecentBySource(string $sourceId, int $limit): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(EventEntity::class, 'e')
            ->where('e.source = :sourceId')
            ->setParameter('sourceId', $sourceId)
            ->orderBy('e.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(static fn(EventEntity $e) => $e->toDomain(), $entities);
    }

    public function deleteByEndpointId(string $endpointId): void
    {
        $eventIds = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(eed.event)')
            ->from(EventEndpointDeliveryEntity::class, 'eed')
            ->where('eed.endpoint = :endpointId')
            ->setParameter('endpointId', $endpointId)
            ->getQuery()
            ->getSingleColumnResult();

        if ($eventIds === []) {
            return;
        }

        $this->entityManager->createQueryBuilder()
            ->delete(EventEntity::class, 'e')
            ->where('e.id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->getQuery()
            ->execute();
    }

    public function updateStatus(string $id, EventStatus $status): void
    {
        $entity = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(EventEntity::class, 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($entity === null) {
            return;
        }

        $entity->setStatus($status);
        $this->entityManager->flush();
    }
}
