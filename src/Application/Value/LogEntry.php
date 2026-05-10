<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class LogEntry
{
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $eventReceivedAt,
        public string $sourceName,
        public string $sourceId,
        public string $endpointId,
        public string $endpointUrl,
        public string $deliveryStatus,
        public int $attemptCount,
        public ?\DateTimeImmutable $latestAttemptAt,
        public ?int $latestAttemptStatusCode,
    ) {}
}
