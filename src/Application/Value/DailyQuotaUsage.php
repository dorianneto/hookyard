<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class DailyQuotaUsage
{
    public function __construct(
        public string $date,
        public int    $count,
    ) {}
}
