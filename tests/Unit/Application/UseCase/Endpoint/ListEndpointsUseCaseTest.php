<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Endpoint\ListEndpointsUseCase;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListEndpointsUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private ListEndpointsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(EndpointRepositoryPort::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase          = new ListEndpointsUseCase($this->repository, $this->sourceRepository, new NullLogger());
    }

    public function testExecuteReturnsList(): void
    {
        $endpoints = [
            new Endpoint('id-1', 'source-id', 'https://example.com/a', new \DateTimeImmutable()),
            new Endpoint('id-2', 'source-id', 'https://example.com/b', new \DateTimeImmutable()),
        ];

        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $this->repository
            ->expects($this->once())
            ->method('findAllBySource')
            ->with('source-id')
            ->willReturn($endpoints);

        $result = $this->useCase->execute('request-id', 'source-id', 'user-id');

        $this->assertSame($endpoints, $result);
        $this->assertCount(2, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEmptyArrayWhenNoEndpoints(): void
    {
        $this->sourceRepository
            ->method('findById')
            ->willReturn($this->createStub(Source::class));

        $this->repository
            ->method('findAllBySource')
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

        $this->repository->expects($this->never())->method('findAllBySource');

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('request-id', 'source-id', 'other-user-id');
    }
}
