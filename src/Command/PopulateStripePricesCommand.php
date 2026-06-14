<?php

namespace App\Command;

use App\Application\UseCase\Stripe\PopulatePricesUseCase;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:populate-stripe-prices',
    description: 'Populate Stripe prices with the plans in the database',
)]
#[WithMonologChannel('hookyard')]
class PopulateStripePricesCommand extends Command
{
    public function __construct(private PopulatePricesUseCase $populatePricesUseCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $requestId = (string) Uuid::v4();

        try {
            $this->populatePricesUseCase->execute($requestId);

            $io->success('Stripe prices populated successfully.');
        } catch (\Exception $e) {
            $io->error('An error occurred while populating Stripe prices: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
