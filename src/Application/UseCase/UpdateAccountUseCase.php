<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Event\AuditableActionEvent;
use App\Application\Port\AuditEventDispatcherPort;
use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\InvalidPasswordException;
use App\Domain\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class UpdateAccountUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly AuditEventDispatcherPort $auditDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param callable(string $plainPassword, string $storedHash): bool $passwordVerifier
     */
    public function execute(
        string $requestId,
        string $userId,
        string $name,
        ?string $newPasswordHash,
        ?string $currentPasswordPlain,
        callable $passwordVerifier,
    ): User {
        $this->logger->info('Update account attempt', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        $user = $this->userRepository->findById($userId);

        if (null === $user) {
            throw new \RuntimeException('User not found.');
        }

        if (null !== $newPasswordHash) {
            if (!$passwordVerifier($currentPasswordPlain ?? '', $user->getPasswordHash())) {
                $this->logger->info('Update account wrong current password', [
                    'request_id' => $requestId,
                    'user_id'    => $userId,
                ]);

                throw new InvalidPasswordException('Current password is incorrect.');
            }
        }

        $updated = new User(
            id:           $user->getId(),
            email:        $user->getEmail(),
            passwordHash: $newPasswordHash ?? $user->getPasswordHash(),
            createdAt:    $user->getCreatedAt(),
            name:         $name,
        );

        $changedFields = [];
        if ($name !== $user->getName()) {
            $changedFields[] = 'name';
        }
        if ($newPasswordHash !== null) {
            $changedFields[] = 'password';
        }

        $this->userRepository->save($updated);

        $this->auditDispatcher->dispatch(new AuditableActionEvent(
            userId:     $userId,
            requestId:  $requestId,
            action:     'edit',
            resource:   'account',
            resourceId: $userId,
            metadata:   ['changed_fields' => $changedFields],
        ));

        $this->logger->info('Update account updated', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        return $updated;
    }
}
