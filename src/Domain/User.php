<?php

declare(strict_types=1);

namespace App\Domain;

class User
{
    public function __construct(
        private string $id,
        private string $email,
        private string $passwordHash,
        private \DateTimeImmutable $createdAt,
        private ?string $name = null,
        private ?string $planId = null,
        private ?string $stripeCustomerId = null,
        private string $status = 'pending_payment',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPlanId(): ?string
    {
        return $this->planId;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
