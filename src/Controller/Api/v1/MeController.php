<?php

declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/me', name: 'me', methods: ['GET'])]
#[WithMonologChannel('hookyard')]
final class MeController
{
    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => 'me',
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => 'me',
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse([
            'id'     => $user->getId(),
            'email'  => $user->getUserIdentifier(),
            'name'   => $user->getName() ?? null,
            'status' => $user->getStatus(),
        ]);
    }
}
