<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\UseCase\ListPlansUseCase;
use App\Domain\Plan;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListPlansUseCaseTest extends TestCase
{
    private PlanRepositoryPort&MockObject $planRepository;
    private ListPlansUseCase $useCase;

    protected function setUp(): void
    {
        $this->planRepository = $this->createMock(PlanRepositoryPort::class);
        $this->useCase        = new ListPlansUseCase($this->planRepository, new NullLogger());
    }

    public function testExecuteReturnsPlanList(): void
    {
        $plans = [
            new Plan('plan-1', 'Starter', 1000, null, new \DateTimeImmutable()),
            new Plan('plan-2', 'Pro', 10000, null, new \DateTimeImmutable()),
        ];

        $this->planRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($plans);

        $result = $this->useCase->execute('request-id');

        $this->assertSame($plans, $result);
    }
}
