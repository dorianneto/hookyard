<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Event as DomainEvent;
use App\Domain\EventStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(name: 'idx_events_source_received', columns: ['source_id', 'received_at'])]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Source::class)]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Source $source;

    #[ORM\Column(type: Types::STRING)]
    private string $method;

    #[ORM\Column(type: Types::JSON)]
    private array $headers;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::STRING, enumType: EventStatus::class)]
    private EventStatus $status;

    #[ORM\Column(name: 'received_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $receivedAt;

    public function __construct(
        string $id,
        Source $source,
        string $method,
        array $headers,
        string $body,
        EventStatus $status,
        \DateTimeImmutable $receivedAt,
    ) {
        $this->id = $id;
        $this->source = $source;
        $this->method = $method;
        $this->headers = $headers;
        $this->body = $body;
        $this->status = $status;
        $this->receivedAt = $receivedAt;
    }

    public static function fromDomain(DomainEvent $event, Source $source): self
    {
        return new self(
            $event->getId(),
            $source,
            $event->getMethod(),
            $event->getHeaders(),
            $event->getBody(),
            $event->getStatus(),
            $event->getReceivedAt(),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setStatus(EventStatus $status): void
    {
        $this->status = $status;
    }

    public function toDomain(): DomainEvent
    {
        return new DomainEvent(
            $this->id,
            $this->source->getId(),
            $this->method,
            $this->headers,
            $this->body,
            $this->status,
            $this->receivedAt,
        );
    }
}
