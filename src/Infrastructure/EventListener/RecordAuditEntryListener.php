<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditRepositoryPort;
use App\Domain\AuditLog;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: AuditableActionEvent::class)]
#[WithMonologChannel('hookyard')]
final class RecordAuditEntryListener
{
    public function __construct(
        private readonly AuditRepositoryPort $auditRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AuditableActionEvent $event): void
    {
        try {
            $log = new AuditLog(
                id:         Uuid::v7()->toRfc4122(),
                userId:     $event->userId,
                action:     $event->action,
                resource:   $event->resource,
                resourceId: $event->resourceId,
                metadata:   $event->metadata,
                createdAt:  new \DateTimeImmutable(),
            );

            $this->auditRepository->save($log);

            $this->logger->debug('Audit entry recorded', [
                'request_id' => $event->requestId,
                'action'     => $event->action,
                'resource'   => $event->resource,
                'user_id'    => $event->userId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record audit entry', [
                'request_id'      => $event->requestId,
                'exception_class' => $e::class,
                'message'         => $e->getMessage(),
            ]);
        }
    }
}
