<?php

declare(strict_types=1);

namespace App\Domain;

class Plan
{
    public function __construct(
        private string $id,
        private string $name,
        private int $monthlyRequestLimit,
        private ?string $stripePriceId,
        private \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setMonthlyRequestLimit(int $monthlyRequestLimit): self
    {
        $this->monthlyRequestLimit = $monthlyRequestLimit;

        return $this;
    }

    public function getMonthlyRequestLimit(): int
    {
        return $this->monthlyRequestLimit;
    }

    public function setStripePriceId(string $stripePriceId): self
    {
        $this->stripePriceId = $stripePriceId;

        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
