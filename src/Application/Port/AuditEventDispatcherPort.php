<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Event\AuditableActionEvent;

interface AuditEventDispatcherPort
{
    public function dispatch(AuditableActionEvent $event): void;
}
