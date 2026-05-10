<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class AuditLog
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $action,
        private string $resource,
        private ?string $resourceId,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
