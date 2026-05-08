<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\EventEndpointDelivery as DomainEventEndpointDelivery;
use App\Domain\EventStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_endpoint_deliveries')]
#[ORM\UniqueConstraint(name: 'uq_eed_event_endpoint', columns: ['event_id', 'endpoint_id'])]
#[ORM\Index(name: 'idx_eed_event_id', columns: ['event_id'])]
class EventEndpointDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: Endpoint::class)]
    #[ORM\JoinColumn(name: 'endpoint_id', referencedColumnName: 'id', nullable: false)]
    private Endpoint $endpoint;

    #[ORM\Column(type: Types::STRING, enumType: EventStatus::class)]
    private EventStatus $status;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        Event $event,
        Endpoint $endpoint,
        EventStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->event = $event;
        $this->endpoint = $endpoint;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(DomainEventEndpointDelivery $delivery, Event $event, Endpoint $endpoint): self
    {
        return new self(
            $delivery->getId(),
            $event,
            $endpoint,
            $delivery->getStatus(),
            $delivery->getCreatedAt(),
            $delivery->getUpdatedAt(),
        );
    }

    public function setStatus(EventStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toDomain(): DomainEventEndpointDelivery
    {
        return new DomainEventEndpointDelivery(
            $this->id,
            $this->event->getId(),
            $this->endpoint->getId(),
            $this->status,
            $this->createdAt,
            $this->updatedAt,
        );
    }
}
