<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Value\LogEntry;
use App\Application\Value\LogFilters;

interface LogRepositoryPort
{
    /** @return LogEntry[] */
    public function findByUser(string $userId, LogFilters $filters): array;

    public function countByUser(string $userId, LogFilters $filters): int;
}
