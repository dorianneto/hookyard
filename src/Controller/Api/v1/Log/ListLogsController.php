<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Log;

use App\Application\UseCase\Log\ListLogsUseCase;
use App\Application\Value\LogEntry;
use App\Application\Value\LogFilters;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/logs', name: 'list_logs', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListLogsController
{
    public function __construct(
        private readonly ListLogsUseCase $listLogsUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'list_logs',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $endpointIds = (array) $request->query->all('endpoint_ids');
        $sourceIds   = (array) $request->query->all('source_ids');
        $status      = $request->query->get('status');
        $page        = max(1, (int) $request->query->get('page', 1));
        $perPage     = min(100, max(1, (int) $request->query->get('per_page', 20)));

        $filters = new LogFilters(
            endpointIds: array_values(array_filter($endpointIds)),
            sourceIds:   array_values(array_filter($sourceIds)),
            status:      $status !== '' ? $status : null,
            page:        $page,
            perPage:     $perPage,
        );

        $result = $this->listLogsUseCase->execute($requestId, $user->getId(), $filters);

        return new JsonResponse([
            'data'    => array_map(static fn(LogEntry $entry) => [
                'eventId'                 => $entry->eventId,
                'eventReceivedAt'         => $entry->eventReceivedAt->format(\DateTimeInterface::ATOM),
                'sourceName'              => $entry->sourceName,
                'sourceId'               => $entry->sourceId,
                'endpointId'             => $entry->endpointId,
                'endpointUrl'            => $entry->endpointUrl,
                'deliveryStatus'         => $entry->deliveryStatus,
                'attemptCount'           => $entry->attemptCount,
                'latestAttemptAt'        => $entry->latestAttemptAt?->format(\DateTimeInterface::ATOM),
                'latestAttemptStatusCode' => $entry->latestAttemptStatusCode,
            ], $result->entries),
            'total'   => $result->total,
            'page'    => $result->page,
            'perPage' => $result->perPage,
        ]);
    }
}
