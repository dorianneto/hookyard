<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Value\AuditEntry;
use App\Application\Value\AuditFilters;
use App\Domain\AuditLog;

interface AuditRepositoryPort
{
    /** @return AuditEntry[] */
    public function findByUser(string $userId, AuditFilters $filters): array;

    public function countByUser(string $userId, AuditFilters $filters): int;

    public function save(AuditLog $auditLog): void;
}
