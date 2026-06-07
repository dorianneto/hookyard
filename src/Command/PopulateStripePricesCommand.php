<?php

namespace App\Command;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:populate-stripe-prices',
    description: 'Populate Stripe prices with the plans in the database',
)]
#[WithMonologChannel('hookyard')]
class PopulateStripePricesCommand extends Command
{
    public function __construct(private ParameterBagInterface $parameterBag, private PlanRepositoryPort $planRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

                $io->success(sprintf('Plan "%s" updated.', $fixedPlan->getName()));
            } else {
                $plansToSave[] = $fixedPlan;
                $io->success(sprintf('Plan "%s" created.', $fixedPlan->getName()));
            }
        }

        foreach ($existingPlans as $existingPlan) {
            $plansToRemove[] = $existingPlan;
            $io->warning(sprintf('Plan "%s" with ID "%s" will be removed because it is not in the fixed plans list.', $existingPlan->getName(), $existingPlan->getId()));
        }

        $this->planRepository->bulkRemove($plansToRemove);
        $this->planRepository->bulkSave($plansToSave);

        return Command::SUCCESS;
    }
}
