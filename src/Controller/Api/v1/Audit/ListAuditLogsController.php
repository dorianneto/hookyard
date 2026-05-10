<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Audit;

use App\Application\UseCase\Audit\ListAuditLogsUseCase;
use App\Application\Value\AuditEntry;
use App\Application\Value\AuditFilters;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/audit', name: 'audit_list', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class ListAuditLogsController
{
    public function __construct(
        private readonly ListAuditLogsUseCase $useCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $dateFrom = null;
        $dateTo   = null;

        if ($raw = $request->query->get('date_from')) {
            $dateFrom = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw);
            if ($dateFrom === false) {
                return new JsonResponse(['error' => 'Invalid date_from format. Expected ISO 8601.'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($raw = $request->query->get('date_to')) {
            $dateTo = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw);
            if ($dateTo === false) {
                return new JsonResponse(['error' => 'Invalid date_to format. Expected ISO 8601.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $filters = new AuditFilters(
            actions:   $request->query->all('action'),
            resources: $request->query->all('resource'),
            dateFrom:  $dateFrom,
            dateTo:    $dateTo,
            page:      max(1, (int) $request->query->get('page', 1)),
            perPage:   min(100, max(1, (int) $request->query->get('per_page', 20))),
        );

        $result = $this->useCase->execute($requestId, $user->getId(), $filters);

        return new JsonResponse([
            'data'     => array_map(fn(AuditEntry $e) => [
                'id'          => $e->id,
                'action'      => $e->action,
                'resource'    => $e->resource,
                'resource_id' => $e->resourceId,
                'metadata'    => $e->metadata,
                'created_at'  => $e->createdAt->format(\DateTimeInterface::ATOM),
            ], $result->entries),
            'total'    => $result->total,
            'page'     => $result->page,
            'per_page' => $result->perPage,
        ]);
    }
}
