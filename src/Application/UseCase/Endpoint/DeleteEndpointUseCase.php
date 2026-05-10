<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\Port\TransactionPort;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class DeleteEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly EventRepositoryPort $eventRepository,
        private readonly TransactionPort $transaction,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $this->logger->info('Delete endpoint attempt', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
        ]);

        $endpoint = $this->endpointRepository->findById($id);

        if ($endpoint === null) {
            $this->logger->info('Delete endpoint not found', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
            ]);

            throw new EndpointNotFoundException('Endpoint not found.');
        }

        $endpointUrl = $endpoint->getUrl();
        $sourceId    = $endpoint->getSourceId();

        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            $this->logger->info('Delete endpoint source not found', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
                'source_id'   => $sourceId,
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        $this->transaction->execute(function () use ($requestId, $id): void {
            $this->eventRepository->deleteByEndpointId($id);

            $this->logger->info('Delete endpoint events deleted', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
            ]);

            $this->endpointRepository->delete($id);
        });

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $userId,
            requestId:  $requestId,
            action:     'delete',
            resource:   'endpoint',
            resourceId: $id,
            metadata:   ['url' => $endpointUrl, 'source_id' => $sourceId],
        ));

        $this->logger->info('Delete endpoint deleted', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
        ]);
    }
}
