<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class AddEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $sourceId, string $url, string $userId): Endpoint
    {
        $this->logger->info('Add endpoint attempt', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
            'source_id'   => $sourceId,
        ]);

        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            $this->logger->info('Add endpoint source not found', [
                'request_id' => $requestId,
                'source_id'  => $sourceId,
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->logger->info('Add endpoint invalid URL', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
            ]);

            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $endpoint = new Endpoint(
            id: $id,
            sourceId: $sourceId,
            url: $url,
            createdAt: new \DateTimeImmutable(),
        );

        $this->endpointRepository->save($endpoint);

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $userId,
            requestId:  $requestId,
            action:     'create',
            resource:   'endpoint',
            resourceId: $id,
            metadata:   ['url' => $url, 'source_id' => $sourceId],
        ));

        $this->logger->info('Add endpoint added', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
            'source_id'   => $sourceId,
        ]);

        return $endpoint;
    }
}
