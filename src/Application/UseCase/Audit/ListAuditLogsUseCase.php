<?php

declare(strict_types=1);

namespace App\Application\UseCase\Audit;

use App\Application\Port\AuditRepositoryPort;
use App\Application\Value\AuditFilters;
use App\Application\Value\AuditResult;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListAuditLogsUseCase
{
    public function __construct(
        private readonly AuditRepositoryPort $auditRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId, AuditFilters $filters): AuditResult
    {
        $this->logger->info('List audit logs attempt', ['request_id' => $requestId, 'user_id' => $userId]);

        $entries = $this->auditRepository->findByUser($userId, $filters);
        $total   = $this->auditRepository->countByUser($userId, $filters);

        $this->logger->info('List audit logs returned', ['request_id' => $requestId, 'total' => $total]);

        return new AuditResult(entries: $entries, total: $total, page: $filters->page, perPage: $filters->perPage);
    }
}
