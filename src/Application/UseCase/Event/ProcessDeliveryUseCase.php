<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\HttpDeliveryPort;
use App\Application\Port\TransactionPort;
use App\Domain\DeliveryAttempt;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[WithMonologChannel('hookyard')]
final class ProcessDeliveryUseCase
{
    private const RETRY_DELAYS_MS = [30_000, 300_000, 1_800_000, 7_200_000];

    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly DeliveryAttemptRepositoryPort $attemptRepository,
        private readonly HttpDeliveryPort $httpDelivery,
        private readonly DeliveryQueuePort $queue,
        private readonly TransactionPort $transaction,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(DeliverEventMessage $message): void
    {
        $endpoint = $this->endpointRepository->findById($message->endpointId);
        $event    = $this->eventRepository->findById($message->eventId);
        $delivery = $this->deliveryRepository->findByEventAndEndpoint($message->eventId, $message->endpointId);

        $this->logger->info('Delivery attempt started', [
            'request_id'     => $message->requestId,
            'event_id'       => $message->eventId,
            'endpoint_id'    => $message->endpointId,
            'attempt_number' => $message->attemptNumber,
            'endpoint_url'   => $endpoint->getUrl(),
        ]);

        $outgoingHeaders = array_merge($event->getHeaders(), [
            'X-Webhook-Event-Id' => [$message->eventId],
        ]);

        $result = $this->httpDelivery->deliver(
            $endpoint->getUrl(),
            $outgoingHeaders,
            $event->getBody(),
            10,
        );

        if ($result->statusCode === null) {
            $this->logger->warning('Delivery transport exception', [
                'request_id'        => $message->requestId,
                'event_id'          => $message->eventId,
                'endpoint_id'       => $message->endpointId,
                'attempt_number'    => $message->attemptNumber,
                'exception_message' => $result->exception?->getMessage() ?? 'Unknown exception',
            ]);
        } elseif ($result->success) {
            $this->logger->info('Delivery HTTP succeeded', [
                'request_id'     => $message->requestId,
                'event_id'       => $message->eventId,
                'endpoint_id'    => $message->endpointId,
                'attempt_number' => $message->attemptNumber,
                'status_code'    => $result->statusCode,
                'duration_ms'    => $result->durationMs,
            ]);
        } else {
            $this->logger->warning('Delivery HTTP failed', [
                'request_id'     => $message->requestId,
                'event_id'       => $message->eventId,
                'endpoint_id'    => $message->endpointId,
                'attempt_number' => $message->attemptNumber,
                'status_code'    => $result->statusCode,
                'duration_ms'    => $result->durationMs,
            ]);
        }

        $attempt = new DeliveryAttempt(
            id: Uuid::v7()->toRfc4122(),
            eventId: $message->eventId,
            endpointId: $message->endpointId,
            attemptNumber: $message->attemptNumber,
            statusCode: $result->statusCode,
            responseBody: $result->responseBody,
            durationMs: $result->durationMs,
            attemptedAt: new \DateTimeImmutable(),
        );

        $this->attemptRepository->save($attempt);

        $newDeliveryStatus = match (true) {
            $result->success                       => EventStatus::Delivered,
            $message->attemptNumber >= 5           => EventStatus::Failed,
            default                                => EventStatus::Pending,
        };

        if (!$result->success && $message->attemptNumber >= 5) {
            $this->logger->error('Delivery marked failed — max attempts reached', [
                'request_id'     => $message->requestId,
                'event_id'       => $message->eventId,
                'endpoint_id'    => $message->endpointId,
                'attempt_number' => $message->attemptNumber,
            ]);
        }

        $this->transaction->execute(function () use ($delivery, $newDeliveryStatus, $message): void {
            $this->deliveryRepository->updateStatus($delivery->getId(), $newDeliveryStatus);
            $allDeliveries = $this->deliveryRepository->findAllByEvent($message->eventId);
            $newEventStatus = $this->computeEventStatus($allDeliveries);
            $this->eventRepository->updateStatus($message->eventId, $newEventStatus);

            $this->logger->info('Delivery event status recomputed', [
                'request_id' => $message->requestId,
                'event_id'   => $message->eventId,
                'new_status' => $newEventStatus->value,
            ]);
        });

        if (!$result->success && $message->attemptNumber < 5) {
            $delayMs = self::RETRY_DELAYS_MS[$message->attemptNumber - 1];
            $this->queue->enqueue(
                new DeliverEventMessage($message->eventId, $message->endpointId, $message->attemptNumber + 1, $message->requestId),
                $delayMs,
            );

            $this->logger->info('Delivery retry enqueued', [
                'request_id'  => $message->requestId,
                'event_id'    => $message->eventId,
                'endpoint_id' => $message->endpointId,
                'next_attempt' => $message->attemptNumber + 1,
                'delay_ms'    => $delayMs,
            ]);
        }
    }

    /** @param EventEndpointDelivery[] $deliveries */
    private function computeEventStatus(array $deliveries): EventStatus
    {
        foreach ($deliveries as $delivery) {
            if ($delivery->getStatus() === EventStatus::Failed) {
                return EventStatus::Failed;
            }
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->getStatus() !== EventStatus::Delivered) {
                return EventStatus::Pending;
            }
        }

        return EventStatus::Delivered;
    }
}
