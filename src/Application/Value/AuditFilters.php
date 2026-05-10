<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class AuditFilters
{
    public function __construct(
        public array $actions = [],
        public array $resources = [],
        public ?\DateTimeImmutable $dateFrom = null,
        public ?\DateTimeImmutable $dateTo = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
