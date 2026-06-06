<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Stripe;

use App\Application\UseCase\ResumeRegistrationUseCase;
use App\Domain\Exception\AccountAlreadyActiveException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Entity\User as UserEntity;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register/resume', name: 'api_v1_register_resume', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class ResumeRegistrationController
{
    public function __construct(
        private readonly ResumeRegistrationUseCase $resumeUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'api_v1_register_resume';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof UserEntity) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $successUrl = $request->getSchemeAndHttpHost() . '/register/success';
        $cancelUrl  = $request->getSchemeAndHttpHost() . '/register/cancel';

        try {
            $checkoutUrl = $this->resumeUseCase->execute(
                requestId:  $requestId,
                userId:     $user->getId(),
                successUrl: $successUrl,
                cancelUrl:  $cancelUrl,
            );
        } catch (AccountAlreadyActiveException $e) {
            $this->logger->info('Resume registration account already active', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(
                ['error' => 'Account is already active.'],
                Response::HTTP_CONFLICT
            );
        } catch (PlanNotConfiguredException $e) {
            $this->logger->error('Resume registration plan not configured', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(
                ['error' => 'No plan to resume. Please register again.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse(['checkout_url' => $checkoutUrl]);
    }
}
