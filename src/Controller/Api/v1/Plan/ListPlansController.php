<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Plan;

use App\Application\UseCase\ListPlansUseCase;
use App\Domain\Plan;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plans', name: 'api_v1_plans_list', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListPlansController
{
    public function __construct(
        private readonly ListPlansUseCase $listPlansUseCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'api_v1_plans_list',
            'method'     => $request->getMethod(),
        ]);

        $plans = $this->listPlansUseCase->execute($requestId);

        return new JsonResponse([
            'data' => array_map(fn(Plan $p) => [
                'id'                    => $p->getId(),
                'name'                  => $p->getName(),
                'monthly_request_limit' => $p->getMonthlyRequestLimit(),
            ], $plans),
        ]);
    }
}
