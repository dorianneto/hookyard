<?php

declare(strict_types=1);

namespace App\Application\Event;

final readonly class AuditableActionEvent
{
    public function __construct(
        public string $userId,
        public string $requestId,
        public string $action,
        public string $resource,
        public ?string $resourceId,
        public array $metadata,
    ) {}
}
