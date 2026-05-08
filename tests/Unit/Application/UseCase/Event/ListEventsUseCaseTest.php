<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Event;

use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Event\ListEventsUseCase;
use App\Domain\Event;
use App\Domain\EventStatus;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListEventsUseCaseTest extends TestCase
{
    private EventRepositoryPort&MockObject $eventRepository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private ListEventsUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository  = $this->createMock(EventRepositoryPort::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase          = new ListEventsUseCase($this->eventRepository, $this->sourceRepository, new NullLogger());
    }

    public function testExecuteReturnsList(): void
    {
        $events = [
            new Event('event-1', 'source-id', 'POST', [], '', EventStatus::Pending, new \DateTimeImmutable()),
            new Event('event-2', 'source-id', 'GET', [], '', EventStatus::Delivered, new \DateTimeImmutable()),
        ];

        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $this->eventRepository
            ->expects($this->once())
            ->method('findRecentBySource')
            ->with('source-id', 100)
            ->willReturn($events);

        $result = $this->useCase->execute('request-id', 'source-id', 'user-id');

        $this->assertSame($events, $result);
        $this->assertCount(2, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEmptyArrayWhenNoEvents(): void
    {
        $this->sourceRepository
            ->method('findById')
            ->willReturn($this->createStub(Source::class));

        $this->eventRepository
            ->method('findRecentBySource')
            ->willReturn([]);

        $result = $this->useCase->execute('request-id', 'source-id', 'user-id');

        $this->assertSame([], $result);
    }

    public function testExecuteThrowsWhenSourceNotOwned(): void
    {
        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->eventRepository->expects($this->never())->method('findRecentBySource');

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('request-id', 'source-id', 'other-user-id');
    }
}
