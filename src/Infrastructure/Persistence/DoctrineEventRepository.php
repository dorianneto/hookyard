<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EventRepositoryPort;
use App\Domain\Event as DomainEvent;
use App\Domain\EventStatus;
use App\Entity\Event as EventEntity;
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
        $entity = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findOneBy(['id' => $id]);

        return $entity?->toDomain();
    }

    /** @return DomainEvent[] */
    public function findRecentBySource(string $sourceId, int $limit): array
    {
        $entities = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findBy(['source' => $sourceId], ['receivedAt' => 'DESC'], $limit);

        return array_map(static fn(EventEntity $e) => $e->toDomain(), $entities);
    }

    public function deleteByEndpointId(string $endpointId): void
    {
        $eventIds = $this->entityManager
            ->createQuery('SELECT IDENTITY(eed.event) FROM App\Entity\EventEndpointDelivery eed WHERE eed.endpoint = :endpointId')
            ->setParameter('endpointId', $endpointId)
            ->getSingleColumnResult();

        if ($eventIds === []) {
            return;
        }

        $this->entityManager
            ->createQuery('DELETE FROM App\Entity\Event e WHERE e.id IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->execute();
    }

    public function updateStatus(string $id, EventStatus $status): void
    {
        $entity = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findOneBy(['id' => $id]);

        if ($entity === null) {
            return;
        }

        $entity->setStatus($status);
        $this->entityManager->flush();
    }
}
