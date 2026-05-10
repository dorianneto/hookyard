<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class LogsResult
{
    /** @param LogEntry[] $entries */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
