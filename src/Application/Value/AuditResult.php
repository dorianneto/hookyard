<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class AuditResult
{
    public function __construct(
        /** @var AuditEntry[] */
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
