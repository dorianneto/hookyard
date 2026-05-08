<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Event;

use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Event\GetEventDetailUseCase;
use App\Application\Value\EventDetail;
use App\Domain\Event;
use App\Domain\EventStatus;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GetEventDetailUseCaseTest extends TestCase
{
    private EventRepositoryPort&MockObject $eventRepository;
    private EventEndpointDeliveryRepositoryPort&MockObject $deliveryRepository;
    private EndpointRepositoryPort&MockObject $endpointRepository;
    private DeliveryAttemptRepositoryPort&MockObject $attemptRepository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private GetEventDetailUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository    = $this->createMock(EventRepositoryPort::class);
        $this->deliveryRepository = $this->createMock(EventEndpointDeliveryRepositoryPort::class);
        $this->endpointRepository = $this->createMock(EndpointRepositoryPort::class);
        $this->attemptRepository  = $this->createMock(DeliveryAttemptRepositoryPort::class);
        $this->sourceRepository   = $this->createMock(SourceRepositoryPort::class);

        $this->useCase = new GetEventDetailUseCase(
            $this->eventRepository,
            $this->deliveryRepository,
            $this->endpointRepository,
            $this->attemptRepository,
            $this->sourceRepository,
            new NullLogger(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsNullWhenEventNotFound(): void
    {
        $this->eventRepository
            ->method('findById')
            ->willReturn(null);

        $result = $this->useCase->execute('request-id', 'missing-id', 'user-id');

        $this->assertNull($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsNullWhenSourceNotOwned(): void
    {
        $event = new Event('event-id', 'source-id', 'POST', [], '', EventStatus::Pending, new \DateTimeImmutable());

        $this->eventRepository
            ->method('findById')
            ->willReturn($event);

        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'other-user-id')
            ->willReturn(null);

        $result = $this->useCase->execute('request-id', 'event-id', 'other-user-id');

        $this->assertNull($result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEventDetailWhenOwned(): void
    {
        $event = new Event('event-id', 'source-id', 'POST', [], '', EventStatus::Pending, new \DateTimeImmutable());

        $this->eventRepository
            ->method('findById')
            ->willReturn($event);

        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $this->deliveryRepository
            ->method('findAllByEvent')
            ->willReturn([]);

        $result = $this->useCase->execute('request-id', 'event-id', 'user-id');

        $this->assertInstanceOf(EventDetail::class, $result);
        $this->assertSame($event, $result->event);
    }
}
