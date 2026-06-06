<?php

declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\Exception\PlanNotFoundException;
use App\Domain\Exception\PlanNotConfiguredException;
use App\Entity\User as UserEntity;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/register', name: 'register', methods: ['POST'])]
#[WithMonologChannel('hookyard')]
final class RegistrationController
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id');
        $route     = 'register';

        $this->logger->info('Request received', [
            'request_id' => $requestId,
            'route'      => $route,
            'method'     => $request->getMethod(),
        ]);

        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email  = $data['email']   ?? '';
        $password = $data['password'] ?? '';
        $planId = $data['plan_id'] ?? '';
        $name   = $data['name']    ?? null;

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(message: 'Email is required.'),
            new Assert\Email(message: 'Invalid email format.'),
        ]);

        if (\count($violations) > 0) {
            $this->logger->warning('Validation failure', [
                'request_id' => $requestId,
                'route'      => $route,
                'violations' => [(string) $violations[0]->getMessage()],
            ]);

            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $violations = $this->validator->validate($password, [
            new Assert\NotBlank(message: 'Password is required.'),
        ]);

        if (\count($violations) > 0) {
            $this->logger->warning('Validation failure', [
                'request_id' => $requestId,
                'route'      => $route,
                'violations' => [(string) $violations[0]->getMessage()],
            ]);

            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $violations = $this->validator->validate($planId, [
            new Assert\NotBlank(message: 'plan_id is required.'),
        ]);

        if (\count($violations) > 0) {
            $this->logger->warning('Validation failure', [
                'request_id' => $requestId,
                'route'      => $route,
                'violations' => [(string) $violations[0]->getMessage()],
            ]);

            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $id       = Uuid::v7()->toRfc4122();
        $tempUser = new UserEntity($id, $email, '', new \DateTimeImmutable());
        $passwordHash = $this->passwordHasher->hashPassword($tempUser, $password);

        $successUrl = $request->getSchemeAndHttpHost() . '/register/success';
        $cancelUrl  = $request->getSchemeAndHttpHost() . '/register/cancel';

        try {
            $checkoutUrl = $this->registerUserUseCase->execute(
                requestId:    $requestId,
                id:           $id,
                email:        $email,
                passwordHash: $passwordHash,
                planId:       $planId,
                successUrl:   $successUrl,
                cancelUrl:    $cancelUrl,
                name:         $name,
            );
        } catch (PlanNotFoundException $e) {
            $this->logger->info('Registration plan not found', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(
                ['error' => 'Plan not found.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (PlanNotConfiguredException $e) {
            $this->logger->error('Registration plan not configured for payment', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(
                ['error' => 'Selected plan is not available for payment.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (EmailAlreadyTakenException $e) {
            $this->logger->info('Registration email already taken', [
                'request_id'      => $requestId,
                'route'           => $route,
                'exception_class' => $e::class,
            ]);

            return new JsonResponse(
                ['error' => 'Email is already registered.'],
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
