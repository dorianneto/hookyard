<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Domain\EventEndpointDelivery as DomainDelivery;
use App\Domain\EventStatus;
use App\Entity\Endpoint as EndpointEntity;
use App\Entity\Event as EventEntity;
use App\Entity\EventEndpointDelivery as DeliveryEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEventEndpointDeliveryRepository implements EventEndpointDeliveryRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainDelivery $delivery): void
    {
        $event = $this->entityManager->getReference(EventEntity::class, $delivery->getEventId());
        $endpoint = $this->entityManager->getReference(EndpointEntity::class, $delivery->getEndpointId());
        $entity = DeliveryEntity::fromDomain($delivery, $event, $endpoint);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByEventAndEndpoint(string $eventId, string $endpointId): ?DomainDelivery
    {
        $entity = $this->entityManager
            ->getRepository(DeliveryEntity::class)
            ->findOneBy(['event' => $eventId, 'endpoint' => $endpointId]);

        return $entity?->toDomain();
    }

    public function updateStatus(string $id, EventStatus $status): void
    {
        $entity = $this->entityManager
            ->getRepository(DeliveryEntity::class)
            ->findOneBy(['id' => $id]);

        if ($entity === null) {
            return;
        }

        $entity->setStatus($status);
        $this->entityManager->flush();
    }

    /** @return DomainDelivery[] */
    public function findAllByEvent(string $eventId): array
    {
        $entities = $this->entityManager
            ->getRepository(DeliveryEntity::class)
            ->findBy(['event' => $eventId]);

        return array_map(static fn(DeliveryEntity $e) => $e->toDomain(), $entities);
    }
}
