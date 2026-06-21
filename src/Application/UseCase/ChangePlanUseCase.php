<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\AlreadyOnPlanException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ChangePlanUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly PlanRepositoryPort $planRepository,
        private readonly StripeServicePort $stripeService,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $userId,
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $this->logger->info('Change plan attempt', [
            'request_id' => $requestId,
            'user_id'    => $userId,
            'plan_id'    => $planId,
        ]);

        $user = $this->userRepository->findById($userId);

        if ($user?->getPlanId() === $planId) {
            $this->logger->info('Change plan already on plan', [
                'request_id' => $requestId,
                'user_id'    => $userId,
                'plan_id'    => $planId,
            ]);

            throw new AlreadyOnPlanException('User is already on the requested plan.');
        }

        $plan = $this->planRepository->findById($planId);

        if (null === $plan) {
            $this->logger->info('Change plan — plan not found', [
                'request_id' => $requestId,
                'plan_id'    => $planId,
            ]);

            throw new PlanNotFoundException('Plan not found.');
        }

        if (null === $plan->getStripePriceId()) {
            $this->logger->warning('Change plan — plan has no stripe_price_id', [
                'request_id' => $requestId,
                'plan_id'    => $planId,
            ]);

            throw new PlanNotConfiguredException('Selected plan is not configured for payment.');
        }

        if (null !== $user?->getStripeSubscriptionId()) {
            $this->logger->info('Change plan — cancelling existing subscription', [
                'request_id'              => $requestId,
                'user_id'                 => $userId,
                'stripe_subscription_id'  => $user->getStripeSubscriptionId(),
            ]);

            $this->stripeService->cancelSubscription($user->getStripeSubscriptionId());
        }

        $checkoutUrl = $this->stripeService->createCheckoutSession(
            stripePriceId: $plan->getStripePriceId(),
            userId:        $userId,
            planId:        $planId,
            successUrl:    $successUrl,
            cancelUrl:     $cancelUrl,
        );

        $this->logger->info('Change plan — checkout session created', [
            'request_id' => $requestId,
            'user_id'    => $userId,
            'plan_id'    => $planId,
        ]);

        return $checkoutUrl;
    }
}
