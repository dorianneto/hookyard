<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Dashboard;

use App\Application\UseCase\Dashboard\GetDashboardChartsUseCase;
use App\Application\Value\DailyEventCount;
use App\Application\Value\DailyQuotaUsage;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/charts', name: 'dashboard_charts', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class DashboardChartsController
{
    public function __construct(
        private readonly GetDashboardChartsUseCase $getDashboardChartsUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'dashboard_charts',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $charts = $this->getDashboardChartsUseCase->execute($requestId, $user->getId());

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'dashboard_charts',
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse([
            'eventsByDay' => array_map(
                fn(DailyEventCount $d) => [
                    'date'      => $d->date,
                    'total'     => $d->total,
                    'delivered' => $d->delivered,
                    'pending'   => $d->pending,
                    'failed'    => $d->failed,
                ],
                $charts->eventsByDay,
            ),
            'quotaByDay' => array_map(
                fn(DailyQuotaUsage $d) => [
                    'date'  => $d->date,
                    'count' => $d->count,
                ],
                $charts->quotaByDay,
            ),
        ]);
    }
}
