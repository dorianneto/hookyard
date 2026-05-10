<?php

declare(strict_types=1);

namespace App\Infrastructure\EventDispatcher;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SymfonyAuditEventDispatcher implements AuditEventDispatcherPort
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function dispatch(AuditableActionEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
