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

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $name,
        int $monthlyRequestLimit,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->monthlyRequestLimit = $monthlyRequestLimit;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function toDomain(): DomainPlan
    {
        return new DomainPlan(
            $this->id,
            $this->name,
            $this->monthlyRequestLimit,
            $this->createdAt,
        );
    }
}
