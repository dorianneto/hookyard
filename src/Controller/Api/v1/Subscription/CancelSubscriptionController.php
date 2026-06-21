<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Subscription;

use App\Application\UseCase\CancelSubscriptionUseCase;
use App\Domain\Exception\NoActiveSubscriptionException;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription', name: 'api_v1_subscription_cancel', methods: ['DELETE'])]
#[WithMonologChannel('hookyard')]
final class CancelSubscriptionController
{
    public function __construct(
        private readonly CancelSubscriptionUseCase $cancelSubscriptionUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'api_v1_subscription_cancel';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->cancelSubscriptionUseCase->execute($requestId, $user->getId());
        } catch (NoActiveSubscriptionException) {
            return new JsonResponse(['error' => 'no_active_subscription'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_NO_CONTENT,
        ]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
