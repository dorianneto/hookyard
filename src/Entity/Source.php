<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Source as DomainSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sources')]
class Source
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\Column(name: 'inbound_uuid', type: Types::GUID, unique: true)]
    private string $inboundUuid;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        User $user,
        string $name,
        string $inboundUuid,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->name = $name;
        $this->inboundUuid = $inboundUuid;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function fromDomain(DomainSource $source, User $user): self
    {
        return new self(
            $source->getId(),
            $user,
            $source->getName(),
            $source->getInboundUuid(),
            $source->getCreatedAt(),
        );
    }

    public function toDomain(): DomainSource
    {
        return new DomainSource(
            $this->id,
            $this->user->getId(),
            $this->name,
            $this->inboundUuid,
            $this->createdAt,
        );
    }
}
