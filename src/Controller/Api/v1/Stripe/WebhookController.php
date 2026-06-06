<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Stripe;

use App\Application\UseCase\HandleStripeWebhookUseCase;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe/webhook', name: 'api_v1_stripe_webhook', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class WebhookController
{
    public function __construct(
        private readonly HandleStripeWebhookUseCase $handleWebhookUseCase,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->warning('Stripe webhook signature invalid', [
                'request_id' => $requestId,
            ]);

            return new JsonResponse(['error' => 'Invalid signature.'], Response::HTTP_BAD_REQUEST);
        }

        if ($event->type !== 'checkout.session.completed') {
            return new JsonResponse([], Response::HTTP_OK);
        }

        $session = $event->data->object;

        $this->handleWebhookUseCase->execute(
            requestId:        $requestId,
            userId:           $session->metadata->user_id,
            stripeCustomerId: $session->customer,
        );

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'api_v1_stripe_webhook',
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse([], Response::HTTP_OK);
    }
}
