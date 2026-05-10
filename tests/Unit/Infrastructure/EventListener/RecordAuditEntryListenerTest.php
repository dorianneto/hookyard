<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\EventListener;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditRepositoryPort;
use App\Domain\AuditLog;
use App\Infrastructure\EventListener\RecordAuditEntryListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RecordAuditEntryListenerTest extends TestCase
{
    private AuditRepositoryPort&MockObject $auditRepository;
    private RecordAuditEntryListener $listener;

    protected function setUp(): void
    {
        $this->auditRepository = $this->createMock(AuditRepositoryPort::class);
        $this->listener        = new RecordAuditEntryListener(
            $this->auditRepository,
            new NullLogger(),
        );
    }

    public function testInvokePersistsDomainAuditLogWithCorrectFields(): void
    {
        $this->auditRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AuditLog $log): bool {
                return $log->getUserId() === 'user-id'
                    && $log->getAction() === 'create'
                    && $log->getResource() === 'source'
                    && $log->getResourceId() === 'res-id';
            }));

        $event = new AuditableActionEvent(
            userId:     'user-id',
            requestId:  'req-id',
            action:     'create',
            resource:   'source',
            resourceId: 'res-id',
            metadata:   [],
        );

        $this->listener->__invoke($event);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInvokeSwallowsExceptionWhenSaveThrows(): void
    {
        $this->auditRepository
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $event = new AuditableActionEvent(
            userId:     'user-id',
            requestId:  'req-id',
            action:     'create',
            resource:   'source',
            resourceId: null,
            metadata:   [],
        );

        $this->listener->__invoke($event);

        $this->addToAssertionCount(1);
    }
}
