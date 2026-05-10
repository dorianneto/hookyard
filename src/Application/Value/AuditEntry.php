<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class AuditEntry
{
    public function __construct(
        public string $id,
        public string $action,
        public string $resource,
        public ?string $resourceId,
        public array $metadata,
        public \DateTimeImmutable $createdAt,
    ) {}
}
