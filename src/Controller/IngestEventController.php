<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Event\IngestEventUseCase;
use App\Domain\Exception\QuotaExceededException;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/in/{uuid}', name: 'ingest_event', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class IngestEventController
{
    public function __construct(
        private readonly IngestEventUseCase $ingestEventUseCase,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $eventId   = Uuid::v7()->toRfc4122();
        $body    = $request->getContent();
        $method  = $request->getMethod();
        $headers = $request->headers->all();

        foreach (['host', 'connection', 'keep-alive', 'transfer-encoding', 'te', 'trailer', 'upgrade', 'proxy-authorization', 'proxy-authenticate', 'content-length'] as $h) {
            unset($headers[$h]);
        }

        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Ingest request received', [
            'request_id'  => $requestId,
            'source_uuid' => $uuid,
            'method'      => $method,
            'body_bytes'  => strlen($body),
        ]);

        try {
            $this->ingestEventUseCase->execute($requestId, $eventId, $uuid, $method, $headers, $body);
        } catch (SourceNotFoundException) {
            $this->logger->info('Ingest source not found', [
                'request_id'  => $requestId,
                'source_uuid' => $uuid,
            ]);

            return new JsonResponse(['error' => 'Source not found.'], Response::HTTP_NOT_FOUND);
        } catch (QuotaExceededException) {
            return new JsonResponse(['error' => 'Request quota exceeded.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
