<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\HttpDeliveryPort;
use App\Application\Port\TransactionPort;
use App\Application\UseCase\Event\ProcessDeliveryUseCase;
use App\Application\Value\DeliveryResult;
use App\Domain\Endpoint;
use App\Domain\Event;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Focused tests for event status recomputation invariants:
 * 1. ALL delivery rows are queried (findAllByEvent), not just the row being updated.
 * 2. Both status updates happen atomically inside the transaction.
 */
final class EventStatusRecomputationTest extends TestCase
{
    private EventRepositoryPort&MockObject $eventRepository;
    private EventEndpointDeliveryRepositoryPort&MockObject $deliveryRepository;
    private EndpointRepositoryPort&MockObject $endpointRepository;
    private DeliveryAttemptRepositoryPort&MockObject $attemptRepository;
    private HttpDeliveryPort&MockObject $httpDelivery;
    private DeliveryQueuePort&MockObject $queue;
    private TransactionPort&MockObject $transaction;
    private ProcessDeliveryUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository    = $this->createMock(EventRepositoryPort::class);
        $this->deliveryRepository = $this->createMock(EventEndpointDeliveryRepositoryPort::class);
        $this->endpointRepository = $this->createMock(EndpointRepositoryPort::class);
        $this->attemptRepository  = $this->createMock(DeliveryAttemptRepositoryPort::class);
        $this->httpDelivery       = $this->createMock(HttpDeliveryPort::class);
        $this->queue              = $this->createMock(DeliveryQueuePort::class);
        $this->transaction        = $this->createMock(TransactionPort::class);

        $this->useCase = new ProcessDeliveryUseCase(
            $this->eventRepository,
            $this->deliveryRepository,
            $this->endpointRepository,
            $this->attemptRepository,
            $this->httpDelivery,
            $this->queue,
            $this->transaction,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAllDeliveryRowsQueriedInsideTransaction(): void
    {
        $this->transaction
            ->method('execute')
            ->willReturnCallback(static fn(callable $op) => $op());

        $this->endpointRepository->method('findById')->willReturn(
            new Endpoint('endpoint-1', 'source-1', 'https://example.com/hook', new \DateTimeImmutable())
        );
        $this->eventRepository->method('findById')->willReturn(
            new Event('event-1', 'source-1', 'POST', [], '{}', EventStatus::Pending, new \DateTimeImmutable())
        );
        $this->deliveryRepository->method('findByEventAndEndpoint')->willReturn(
            new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Pending, new \DateTimeImmutable(), new \DateTimeImmutable())
        );
        $this->httpDelivery->method('deliver')->willReturn(new DeliveryResult(200, 'OK', 50, true));

        // This is the key assertion: findAllByEvent must be called once with the event ID,
        // proving that ALL delivery rows are queried for status recomputation.
        $this->deliveryRepository
            ->expects($this->once())
            ->method('findAllByEvent')
            ->with('event-1')
            ->willReturn([
                new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Delivered, new \DateTimeImmutable(), new \DateTimeImmutable()),
            ]);

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1, 'request-id'));
    }

    public function testStatusUpdatesAreAtomicInsideTransaction(): void
    {
        // Transaction intentionally does NOT invoke the callback.
        // This proves both status updates are inside the transaction boundary —
        // if the transaction doesn't run, neither update fires.
        $this->transaction
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(static function (callable $op): void {});

        $this->endpointRepository->expects($this->once())->method('findById')->willReturn(
            new Endpoint('endpoint-1', 'source-1', 'https://example.com/hook', new \DateTimeImmutable())
        );
        $this->eventRepository->expects($this->once())->method('findById')->willReturn(
            new Event('event-1', 'source-1', 'POST', [], '{}', EventStatus::Pending, new \DateTimeImmutable())
        );
        $this->deliveryRepository->expects($this->once())->method('findByEventAndEndpoint')->willReturn(
            new EventEndpointDelivery('d1', 'event-1', 'endpoint-1', EventStatus::Pending, new \DateTimeImmutable(), new \DateTimeImmutable())
        );
        $this->httpDelivery->expects($this->once())->method('deliver')->willReturn(new DeliveryResult(200, 'OK', 50, true));
        $this->attemptRepository->expects($this->once())->method('save');
        $this->queue->expects($this->never())->method('enqueue');

        $this->deliveryRepository->expects($this->never())->method('updateStatus');
        $this->eventRepository->expects($this->never())->method('updateStatus');

        $this->useCase->execute(new DeliverEventMessage('event-1', 'endpoint-1', 1, 'request-id'));
    }
}
