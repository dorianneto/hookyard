<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\User as DomainUser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_users_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: Types::STRING)]
    private string $passwordHash;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: true)]
    private ?Plan $plan = null;

    #[ORM\Column(name: 'stripe_customer_id', type: Types::STRING, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 50, options: ['default' => 'pending_payment'])]
    private string $status = 'pending_payment';

    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
        ?string $name = null,
        ?Plan $plan = null,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = $createdAt;
        $this->name = $name;
        $this->plan = $plan;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getPlanId(): ?string
    {
        return $this->plan?->getId();
    }

    public function setPlan(?Plan $plan): void
    {
        $this->plan = $plan;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): void
    {
        $this->stripeCustomerId = $stripeCustomerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    // --- PasswordAuthenticatedUserInterface ---

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user->getUserIdentifier() === $this->getUserIdentifier();
    }

    // --- Session serialization: keep password hash out of the session ---

    public function __serialize(): array
    {
        return ['id' => $this->id, 'email' => $this->email];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->passwordHash = '';
        $this->createdAt = new \DateTimeImmutable('@0');
    }

    // --- Mapping helpers ---

    public static function fromDomain(DomainUser $user, ?Plan $plan = null): self
    {
        $entity = new self(
            $user->getId(),
            $user->getEmail(),
            $user->getPasswordHash(),
            $user->getCreatedAt(),
            $user->getName(),
            $plan,
        );
        $entity->stripeCustomerId = $user->getStripeCustomerId();
        $entity->status           = $user->getStatus();

        return $entity;
    }

    public function toDomain(): DomainUser
    {
        return new DomainUser(
            $this->id,
            $this->email,
            $this->passwordHash,
            $this->createdAt,
            $this->name,
            $this->plan?->getId(),
            $this->stripeCustomerId,
            $this->status,
        );
    }
}
