<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Plan as DomainPlan;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $name;

    #[ORM\Column(name: 'monthly_request_limit', type: Types::INTEGER)]
    private int $monthlyRequestLimit;

    #[ORM\Column(name: 'stripe_price_id', type: Types::STRING, nullable: true)]
    private ?string $stripePriceId = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $name,
        int $monthlyRequestLimit,
        \DateTimeImmutable $createdAt,
        ?string $stripePriceId = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->monthlyRequestLimit = $monthlyRequestLimit;
        $this->createdAt = $createdAt;
        $this->stripePriceId = $stripePriceId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function fromDomain(DomainPlan $plan): self
    {
        return new self(
            $plan->getId(),
            $plan->getName(),
            $plan->getMonthlyRequestLimit(),
            $plan->getCreatedAt(),
            $plan->getStripePriceId(),
        );
    }

    public function updateFromDomain(DomainPlan $plan): void
    {
        $this->name = $plan->getName();
        $this->monthlyRequestLimit = $plan->getMonthlyRequestLimit();
        $this->stripePriceId = $plan->getStripePriceId();
    }

    public function toDomain(): DomainPlan
    {
        return new DomainPlan(
            $this->id,
            $this->name,
            $this->monthlyRequestLimit,
            $this->stripePriceId,
            $this->createdAt,
        );
    }
}
