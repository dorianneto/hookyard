<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Source;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[WithMonologChannel('hookyard')]
final class CreateSourceUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $userId, string $name): Source
    {
        $this->logger->info('Create source attempt', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);

        $inboundUuid = Uuid::v7()->toRfc4122();

        $source = new Source(
            id: $id,
            userId: $userId,
            name: $name,
            inboundUuid: $inboundUuid,
            createdAt: new \DateTimeImmutable(),
        );

        $this->sourceRepository->save($source);

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $userId,
            requestId:  $requestId,
            action:     'create',
            resource:   'source',
            resourceId: $id,
            metadata:   ['name' => $name],
        ));

        $this->logger->info('Create source created', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);

        return $source;
    }
}
