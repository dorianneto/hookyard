<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\DeliveryAttempt as DomainDeliveryAttempt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_attempts')]
#[ORM\Index(name: 'idx_da_event_endpoint', columns: ['event_id', 'endpoint_id'])]
class DeliveryAttempt
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

    #[ORM\Column(name: 'attempt_number', type: Types::INTEGER)]
    private int $attemptNumber;

    #[ORM\Column(name: 'status_code', type: Types::INTEGER, nullable: true)]
    private ?int $statusCode;

    #[ORM\Column(name: 'response_body', type: Types::STRING, length: 500)]
    private string $responseBody;

    #[ORM\Column(name: 'duration_ms', type: Types::INTEGER)]
    private int $durationMs;

    #[ORM\Column(name: 'attempted_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $attemptedAt;

    public function __construct(
        string $id,
        Event $event,
        Endpoint $endpoint,
        int $attemptNumber,
        ?int $statusCode,
        string $responseBody,
        int $durationMs,
        \DateTimeImmutable $attemptedAt,
    ) {
        $this->id = $id;
        $this->event = $event;
        $this->endpoint = $endpoint;
        $this->attemptNumber = $attemptNumber;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->durationMs = $durationMs;
        $this->attemptedAt = $attemptedAt;
    }

    public static function fromDomain(DomainDeliveryAttempt $attempt, Event $event, Endpoint $endpoint): self
    {
        return new self(
            $attempt->getId(),
            $event,
            $endpoint,
            $attempt->getAttemptNumber(),
            $attempt->getStatusCode(),
            $attempt->getResponseBody(),
            $attempt->getDurationMs(),
            $attempt->getAttemptedAt(),
        );
    }

    public function toDomain(): DomainDeliveryAttempt
    {
        return new DomainDeliveryAttempt(
            $this->id,
            $this->event->getId(),
            $this->endpoint->getId(),
            $this->attemptNumber,
            $this->statusCode,
            $this->responseBody,
            $this->durationMs,
            $this->attemptedAt,
        );
    }
}
