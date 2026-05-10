<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\SourceRepositoryPort;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class DeleteSourceUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $this->logger->info('Delete source attempt', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);

        $this->sourceRepository->delete($id, $userId);

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $userId,
            requestId:  $requestId,
            action:     'delete',
            resource:   'source',
            resourceId: $id,
            metadata:   ['source_id' => $id],
        ));

        $this->logger->info('Delete source deleted', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);
    }
}
