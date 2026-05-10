<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\AuditLog as DomainAuditLog;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_audit_logs_user_created')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_logs_action')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $resource;

    #[ORM\Column(name: 'resource_id', type: Types::STRING, nullable: true)]
    private ?string $resourceId;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        User $user,
        string $action,
        string $resource,
        ?string $resourceId,
        array $metadata,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id         = $id;
        $this->user       = $user;
        $this->action     = $action;
        $this->resource   = $resource;
        $this->resourceId = $resourceId;
        $this->metadata   = $metadata;
        $this->createdAt  = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function fromDomain(DomainAuditLog $log, User $user): self
    {
        return new self(
            $log->getId(),
            $user,
            $log->getAction(),
            $log->getResource(),
            $log->getResourceId(),
            $log->getMetadata(),
            $log->getCreatedAt(),
        );
    }

    public function toDomain(): DomainAuditLog
    {
        return new DomainAuditLog(
            id:         $this->id,
            userId:     $this->user->getId(),
            action:     $this->action,
            resource:   $this->resource,
            resourceId: $this->resourceId,
            metadata:   $this->metadata,
            createdAt:  $this->createdAt,
        );
    }
}
