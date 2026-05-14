<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Endpoint as DomainEndpoint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'endpoints')]
class Endpoint
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Source::class)]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Source $source;

    #[ORM\Column(type: Types::STRING)]
    private string $url;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        Source $source,
        string $url,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->source = $source;
        $this->url = $url;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function fromDomain(DomainEndpoint $endpoint, Source $source): self
    {
        return new self(
            $endpoint->getId(),
            $source,
            $endpoint->getUrl(),
            $endpoint->getCreatedAt(),
        );
    }

    public function toDomain(): DomainEndpoint
    {
        return new DomainEndpoint(
            $this->id,
            $this->source->getId(),
            $this->url,
            $this->createdAt,
        );
    }
}
