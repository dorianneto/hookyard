<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unexpected user type.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'id'     => $user->getId(),
            'email'  => $user->getUserIdentifier(),
            'name'   => $user->getName() ?? null,
            'status' => $user->getStatus(),
        ], Response::HTTP_OK);
    }
}
