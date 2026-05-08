<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Endpoint\AddEndpointUseCase;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AddEndpointUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private AddEndpointUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(EndpointRepositoryPort::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase          = new AddEndpointUseCase($this->repository, $this->sourceRepository, new NullLogger());
    }

    public function testExecuteSavesEndpointWithCorrectData(): void
    {
        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Endpoint $endpoint): bool {
                return $endpoint->getId() === 'test-id'
                    && $endpoint->getSourceId() === 'source-id'
                    && $endpoint->getUrl() === 'https://example.com/hook';
            }));

        $this->useCase->execute('request-id', 'test-id', 'source-id', 'https://example.com/hook', 'user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEndpoint(): void
    {
        $this->sourceRepository
            ->method('findById')
            ->willReturn($this->createStub(Source::class));

        $result = $this->useCase->execute('request-id', 'test-id', 'source-id', 'https://example.com/hook', 'user-id');

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertSame('test-id', $result->getId());
        $this->assertSame('https://example.com/hook', $result->getUrl());
    }

    public function testExecuteThrowsWhenSourceNotOwned(): void
    {
        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects($this->never())->method('save');

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('request-id', 'test-id', 'source-id', 'https://example.com/hook', 'other-user-id');
    }

    public function testExecuteThrowsOnInvalidUrl(): void
    {
        $this->sourceRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->createStub(Source::class));

        $this->repository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL format.');

        $this->useCase->execute('request-id', 'test-id', 'source-id', 'not-a-valid-url', 'user-id');
    }
}
