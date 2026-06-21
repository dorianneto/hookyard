<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\NoActiveSubscriptionException;
use App\Domain\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class CancelSubscriptionUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly StripeServicePort $stripeService,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId): void
    {
        $this->logger->info('Cancel subscription attempt', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        $user = $this->userRepository->findById($userId);

        if (null === $user?->getStripeSubscriptionId()) {
            $this->logger->info('Cancel subscription — no active subscription', [
                'request_id' => $requestId,
                'user_id'    => $userId,
            ]);

            throw new NoActiveSubscriptionException('No active subscription to cancel.');
        }

        $this->stripeService->cancelSubscription($user->getStripeSubscriptionId());

        $updatedUser = new User(
            id:                   $user->getId(),
            email:                $user->getEmail(),
            passwordHash:         $user->getPasswordHash(),
            createdAt:            $user->getCreatedAt(),
            name:                 $user->getName(),
            planId:               null,
            stripeCustomerId:     $user->getStripeCustomerId(),
            status:               'cancelled',
            stripeSubscriptionId: null,
        );

        $this->userRepository->save($updatedUser);

        $this->logger->info('Subscription cancelled', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);
    }
}
