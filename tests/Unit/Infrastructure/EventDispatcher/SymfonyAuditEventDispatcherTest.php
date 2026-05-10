<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\EventDispatcher;

use App\Application\Event\AuditableActionEvent;
use App\Infrastructure\EventDispatcher\SymfonyAuditEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SymfonyAuditEventDispatcherTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private SymfonyAuditEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->dispatcher      = new SymfonyAuditEventDispatcher($this->eventDispatcher);
    }

    public function testDispatchDelegatesToSymfonyEventDispatcher(): void
    {
        $event = new AuditableActionEvent(
            userId:     'user-id',
            requestId:  'req-id',
            action:     'create',
            resource:   'source',
            resourceId: 'res-id',
            metadata:   [],
        );

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->dispatcher->dispatch($event);
    }
}
