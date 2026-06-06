<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\AccountAlreadyActiveException;
use App\Domain\Exception\PlanNotConfiguredException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ResumeRegistrationUseCase
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
        string $successUrl,
        string $cancelUrl,
    ): string {
        $this->logger->info('Resume registration attempt', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        $user = $this->userRepository->findById($userId);

        if ($user->getStatus() === 'active') {
            throw new AccountAlreadyActiveException('Account is already active.');
        }

        $plan = $this->planRepository->findById($user->getPlanId() ?? '');

        if (null === $plan || null === $plan->getStripePriceId()) {
            throw new PlanNotConfiguredException('No valid plan to resume checkout for.');
        }

        $checkoutUrl = $this->stripeService->createCheckoutSession(
            stripePriceId: $plan->getStripePriceId(),
            userId:        $userId,
            planId:        $plan->getId(),
            successUrl:    $successUrl,
            cancelUrl:     $cancelUrl,
        );

        $this->logger->info('Resume registration checkout session created', [
            'request_id' => $requestId,
            'user_id'    => $userId,
            'plan_id'    => $plan->getId(),
        ]);

        return $checkoutUrl;
    }
}
