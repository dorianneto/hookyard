<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class LogFilters
{
    public function __construct(
        public array $endpointIds = [],
        public array $sourceIds = [],
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
