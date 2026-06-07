<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Plan;

interface PlanRepositoryPort
{
    public function findByUserId(string $userId): ?Plan;

    public function findById(string $id): ?Plan;

    /** @return Plan[] */
    public function findAll(): array;

    /**
     * @param Plan[] $plans
     */
    public function bulkRemove(array $plans): void;

    /**
     * @param Plan[] $plans
     */
    public function bulkSave(array $plans): void;

}
