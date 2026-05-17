<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class DailyEventCount
{
    public function __construct(
        public string $date,
        public int    $total,
        public int    $delivered,
        public int    $pending,
        public int    $failed,
    ) {}
}
