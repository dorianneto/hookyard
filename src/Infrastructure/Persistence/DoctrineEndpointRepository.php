<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EndpointRepositoryPort;
use App\Domain\Endpoint as DomainEndpoint;
use App\Domain\Exception\EndpointNotFoundException;
use App\Entity\Endpoint as EndpointEntity;
use App\Entity\Source as SourceEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEndpointRepository implements EndpointRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainEndpoint $endpoint): void
    {
        $source = $this->entityManager->getReference(SourceEntity::class, $endpoint->getSourceId());
        $entity = EndpointEntity::fromDomain($endpoint, $source);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?DomainEndpoint
    {
        $entity = $this->entityManager
            ->getRepository(EndpointEntity::class)
            ->findOneBy(['id' => $id]);

        return $entity?->toDomain();
    }

    /** @return DomainEndpoint[] */
    public function findAllBySource(string $sourceId): array
    {
        $entities = $this->entityManager
            ->getRepository(EndpointEntity::class)
            ->findBy(['source' => $sourceId]);

        return array_map(static fn(EndpointEntity $e) => $e->toDomain(), $entities);
    }

    /** @return DomainEndpoint[] */
    public function findActiveBySource(string $sourceId): array
    {
        return $this->findAllBySource($sourceId);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager
            ->getRepository(EndpointEntity::class)
            ->findOneBy(['id' => $id]);

        if ($entity === null) {
            throw new EndpointNotFoundException('Endpoint not found.');
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
