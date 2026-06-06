<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\StripeServicePort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Domain\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly PlanRepositoryPort $planRepository,
        private readonly StripeServicePort $stripeService,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $id,
        string $email,
        string $passwordHash,
        string $planId,
        string $successUrl,
        string $cancelUrl,
        ?string $name = null,
    ): string {
        $this->logger->info('Register user attempt', ['request_id' => $requestId]);

        $plan = $this->planRepository->findById($planId);

        if (null === $plan) {
            $this->logger->info('Register user plan not found', [
                'request_id' => $requestId,
                'plan_id'    => $planId,
            ]);

            throw new PlanNotFoundException('Plan not found.');
        }

        if (null === $plan->getStripePriceId()) {
            $this->logger->error('Register user plan has no stripe_price_id', [
                'request_id' => $requestId,
                'plan_id'    => $planId,
            ]);

            throw new PlanNotConfiguredException('Selected plan is not configured for payment.');
        }

        $existing = $this->userRepository->findByEmail($email);

        if (null !== $existing) {
            if ($existing->getStatus() === 'active') {
                $this->logger->info('Register user email already taken by active account', [
                    'request_id' => $requestId,
                ]);

                throw new EmailAlreadyTakenException('Email is already registered.');
            }

            $this->logger->info('Register user retry for pending_payment account', [
                'request_id' => $requestId,
                'user_id'    => $existing->getId(),
            ]);

            $checkoutUrl = $this->stripeService->createCheckoutSession(
                stripePriceId: $plan->getStripePriceId(),
                userId:        $existing->getId(),
                planId:        $plan->getId(),
                successUrl:    $successUrl,
                cancelUrl:     $cancelUrl,
            );

            $this->logger->info('Register user retry checkout session created', [
                'request_id' => $requestId,
                'user_id'    => $existing->getId(),
            ]);

            return $checkoutUrl;
        }

        $user = new User(
            id:           $id,
            email:        $email,
            passwordHash: $passwordHash,
            createdAt:    new \DateTimeImmutable(),
            name:         $name,
            planId:       $plan->getId(),
            status:       'pending_payment',
        );

        $this->userRepository->save($user);

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $id,
            requestId:  $requestId,
            action:     'create',
            resource:   'account',
            resourceId: $id,
            metadata:   ['email' => $email],
        ));

        $checkoutUrl = $this->stripeService->createCheckoutSession(
            stripePriceId: $plan->getStripePriceId(),
            userId:        $id,
            planId:        $plan->getId(),
            successUrl:    $successUrl,
            cancelUrl:     $cancelUrl,
        );

        $this->logger->info('Register user registered', [
            'request_id' => $requestId,
            'user_id'    => $id,
        ]);

        return $checkoutUrl;
    }
}
