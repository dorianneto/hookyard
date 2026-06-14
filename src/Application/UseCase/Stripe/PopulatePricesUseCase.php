<?php

declare(strict_types=1);

namespace App\Application\UseCase\Stripe;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[WithMonologChannel('hookyard')]
final class PopulatePricesUseCase
{
    public function __construct(
        private readonly PlanRepositoryPort $planRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId): void {
        $this->logger->info('Start populating Stripe prices', [
            'request_id' => $requestId,
        ]);

        $developerPriceId = $this->parameterBag->get('app.stripe.developer_price_id');
        $startupPriceId = $this->parameterBag->get('app.stripe.startup_price_id');
        $proPriceId = $this->parameterBag->get('app.stripe.pro_price_id');

        $plans = $this->planRepository->findAll();

        /** @var Plan[] $plansToSave */
        $plansToSave = [];

        /** @var Plan[] $plansToRemove */
        $plansToRemove = [];

        $existingPlans = array_reduce($plans, function (array $carry, Plan $plan) {
            $carry[$plan->getId()] = $plan;

            return $carry;
        }, []);

        $fixedPlans = [new Plan(
            id: 'plan_developer',
            name: 'Developer',
            monthlyRequestLimit: 1000,
            stripePriceId: $developerPriceId,
            createdAt: new \DateTimeImmutable(),
        ), new Plan(
            id: 'plan_startup',
            name: 'Startup',
            monthlyRequestLimit: 10000,
            stripePriceId: $startupPriceId,
            createdAt: new \DateTimeImmutable(),
        ), new Plan(
            id: 'plan_pro',
            name: 'Pro',
            monthlyRequestLimit: 100000,
            stripePriceId: $proPriceId,
            createdAt: new \DateTimeImmutable(),
        )];

        foreach ($fixedPlans as $fixedPlan) {
            if (isset($existingPlans[$fixedPlan->getId()])) {
                /** @var Plan $existingPlan */
                $existingPlan = $existingPlans[$fixedPlan->getId()];

                unset($existingPlans[$fixedPlan->getId()]);

                $plansToSave[] = $existingPlan->setName($fixedPlan->getName())
                    ->setMonthlyRequestLimit($fixedPlan->getMonthlyRequestLimit())
                    ->setStripePriceId($fixedPlan->getStripePriceId());

                $this->logger->info(sprintf('Plan "%s" updated.', $fixedPlan->getName()), [
                    'request_id' => $requestId,
                ]);
            } else {
                $plansToSave[] = $fixedPlan;
                $this->logger->info(sprintf('Plan "%s" created.', $fixedPlan->getName()), [
                    'request_id' => $requestId,
                ]);
            }
        }

        foreach ($existingPlans as $existingPlan) {
            $plansToRemove[] = $existingPlan;
            $this->logger->warning(sprintf('Plan "%s" with ID "%s" will be removed because it is not in the fixed plans list.', $existingPlan->getName(), $existingPlan->getId()), [
                'request_id' => $requestId,
            ]);
        }

        $this->planRepository->bulkRemove($plansToRemove);
        $this->planRepository->bulkSave($plansToSave);

        $this->logger->info('Finished populating Stripe prices', [
            'request_id' => $requestId,
        ]);
    }
}
