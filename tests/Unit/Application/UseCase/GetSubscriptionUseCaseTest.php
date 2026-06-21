<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\GetSubscriptionUseCase;
use App\Domain\Plan;
use App\Domain\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GetSubscriptionUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $userRepository;
    private PlanRepositoryPort&MockObject $planRepository;
    private GetSubscriptionUseCase $useCase;

    private Plan $developer;
    private Plan $startup;
    private Plan $pro;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryPort::class);
        $this->planRepository = $this->createMock(PlanRepositoryPort::class);
        $this->useCase        = new GetSubscriptionUseCase(
            $this->userRepository,
            $this->planRepository,
            new NullLogger(),
        );

        $this->developer = new Plan('plan_developer', 'Developer', 1000, 'price_dev', new \DateTimeImmutable());
        $this->startup   = new Plan('plan_startup', 'Startup', 10000, 'price_startup', new \DateTimeImmutable());
        $this->pro       = new Plan('plan_pro', 'Pro', 100000, 'price_pro', new \DateTimeImmutable());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsCurrentPlanAndAllPlans(): void
    {
        $user = new User(
            id:      'user-id',
            email:   'user@example.com',
            passwordHash: 'hash',
            createdAt: new \DateTimeImmutable(),
            planId:  'plan_startup',
            status:  'active',
        );

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findAll')->willReturn([$this->developer, $this->startup, $this->pro]);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame('active', $result['status']);
        $this->assertSame('plan_startup', $result['current_plan']['id']);
        $this->assertSame('Startup', $result['current_plan']['name']);
        $this->assertSame(10000, $result['current_plan']['monthly_request_limit']);
        $this->assertCount(3, $result['available_plans']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCurrentPlanIsNullWhenUserHasNoPlan(): void
    {
        $user = new User(
            id:           'user-id',
            email:        'user@example.com',
            passwordHash: 'hash',
            createdAt:    new \DateTimeImmutable(),
            status:       'cancelled',
        );

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findAll')->willReturn([$this->developer, $this->startup, $this->pro]);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertNull($result['current_plan']);
        $this->assertSame('cancelled', $result['status']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAvailablePlansContainsCorrectShape(): void
    {
        $user = new User(
            id:           'user-id',
            email:        'user@example.com',
            passwordHash: 'hash',
            createdAt:    new \DateTimeImmutable(),
            planId:       'plan_developer',
            status:       'active',
        );

        $this->userRepository->method('findById')->willReturn($user);
        $this->planRepository->method('findAll')->willReturn([$this->developer]);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame([
            'id'                    => 'plan_developer',
            'name'                  => 'Developer',
            'monthly_request_limit' => 1000,
        ], $result['available_plans'][0]);
    }
}
