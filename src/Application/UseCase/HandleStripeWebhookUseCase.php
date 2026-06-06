<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\UserRepositoryPort;
use App\Domain\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class HandleStripeWebhookUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $userId,
        string $stripeCustomerId,
    ): void {
        $this->logger->info('Handle Stripe webhook', [
            'request_id'         => $requestId,
            'user_id'            => $userId,
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        $user = $this->userRepository->findById($userId);

        if (null === $user) {
            $this->logger->warning('Stripe webhook user not found', [
                'request_id' => $requestId,
                'user_id'    => $userId,
            ]);

            return;
        }

        $updatedUser = new User(
            id:               $user->getId(),
            email:            $user->getEmail(),
            passwordHash:     $user->getPasswordHash(),
            createdAt:        $user->getCreatedAt(),
            name:             $user->getName(),
            planId:           $user->getPlanId(),
            stripeCustomerId: $stripeCustomerId,
            status:           'active',
        );

        $this->userRepository->save($updatedUser);

        $this->logger->info('Stripe webhook user activated', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);
    }
}
