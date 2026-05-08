<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'request_usage')]
#[ORM\UniqueConstraint(name: 'UNIQ_request_usage_user_date', columns: ['user_id', 'bucket_date'])]
class RequestUsage
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(name: 'bucket_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $bucketDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $count;

    public function __construct(
        User $user,
        \DateTimeImmutable $bucketDate,
        int $count = 0,
    ) {
        $this->user = $user;
        $this->bucketDate = $bucketDate;
        $this->count = $count;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }

    public function getBucketDate(): \DateTimeImmutable
    {
        return $this->bucketDate;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
