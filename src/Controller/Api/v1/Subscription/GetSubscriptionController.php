<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Subscription;

use App\Application\UseCase\GetSubscriptionUseCase;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription', name: 'api_v1_subscription_get', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class GetSubscriptionController
{
    public function __construct(
        private readonly GetSubscriptionUseCase $getSubscriptionUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'api_v1_subscription_get',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = $this->getSubscriptionUseCase->execute($requestId, $user->getId());

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'api_v1_subscription_get',
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse($data);
    }
}
