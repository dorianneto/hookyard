<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListPlansUseCase
{
    public function __construct(
        private readonly PlanRepositoryPort $planRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return Plan[] */
    public function execute(string $requestId): array
    {
        $this->logger->info('List plans', ['request_id' => $requestId]);

        return $this->planRepository->findAll();
    }
}
