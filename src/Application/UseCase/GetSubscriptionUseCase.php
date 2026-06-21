<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Plan;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class GetSubscriptionUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly PlanRepositoryPort $planRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{current_plan: array{id: string, name: string, monthly_request_limit: int}|null, available_plans: list<array{id: string, name: string, monthly_request_limit: int}>, status: string}
     */
    public function execute(string $requestId, string $userId): array
    {
        $this->logger->info('Get subscription', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        $user  = $this->userRepository->findById($userId);
        $plans = $this->planRepository->findAll();

        $currentPlan = null;

        if (null !== $user && null !== $user->getPlanId()) {
            foreach ($plans as $plan) {
                if ($plan->getId() === $user->getPlanId()) {
                    $currentPlan = $this->planToArray($plan);
                    break;
                }
            }
        }

        return [
            'current_plan'   => $currentPlan,
            'available_plans' => array_map($this->planToArray(...), $plans),
            'status'          => $user?->getStatus() ?? 'pending_payment',
        ];
    }

    /** @return array{id: string, name: string, monthly_request_limit: int} */
    private function planToArray(Plan $plan): array
    {
        return [
            'id'                    => $plan->getId(),
            'name'                  => $plan->getName(),
            'monthly_request_limit' => $plan->getMonthlyRequestLimit(),
        ];
    }
}
