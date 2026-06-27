<?php

declare(strict_types=1);

namespace App\Controller\Api\v1\Subscription;

use App\Application\UseCase\ChangePlanUseCase;
use App\Domain\Exception\AlreadyOnPlanException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscription/change-plan', name: 'api_v1_subscription_change_plan', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class ChangePlanController
{
    public function __construct(
        private readonly ChangePlanUseCase $changePlanUseCase,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'api_v1_subscription_change_plan';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data) || empty($data['plan_id'])) {
            return new JsonResponse(['error' => 'plan_id is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $baseUrl    = $request->getSchemeAndHttpHost();
        $successUrl = $baseUrl . '/subscription?changed=1';
        $cancelUrl  = $baseUrl . '/subscription';

        try {
            $checkoutUrl = $this->changePlanUseCase->execute(
                requestId:  $requestId,
                userId:     $user->getId(),
                planId:     $data['plan_id'],
                successUrl: $successUrl,
                cancelUrl:  $cancelUrl,
            );
        } catch (AlreadyOnPlanException) {
            return new JsonResponse(['error' => 'already_on_plan'], Response::HTTP_BAD_REQUEST);
        } catch (PlanNotFoundException) {
            return new JsonResponse(['error' => 'plan_not_found'], Response::HTTP_NOT_FOUND);
        } catch (PlanNotConfiguredException) {
            return new JsonResponse(['error' => 'plan_not_configured'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (StripeApiErrorException $e) {
            $this->logger->error('Stripe API error', [
                'request_id' => $requestId,
                'route'      => $route,
                'method'     => $request->getMethod(),
                'error'      => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'stripe_api_error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->logger->info('Response dispatched', [
            'request_id'  => $requestId,
            'route'       => $route,
            'http_status' => Response::HTTP_OK,
        ]);

        return new JsonResponse(['checkout_url' => $checkoutUrl]);
    }
}
