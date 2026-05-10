<?php

declare(strict_types=1);

namespace App\Application\UseCase\Log;

use App\Application\Port\LogRepositoryPort;
use App\Application\Value\LogFilters;
use App\Application\Value\LogsResult;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListLogsUseCase
{
    public function __construct(
        private readonly LogRepositoryPort $logRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId, LogFilters $filters): LogsResult
    {
        $this->logger->info('List logs attempt', [
            'request_id' => $requestId,
            'user_id'    => $userId,
        ]);

        $entries = $this->logRepository->findByUser($userId, $filters);
        $total   = $this->logRepository->countByUser($userId, $filters);

        $this->logger->info('List logs returned', [
            'request_id' => $requestId,
            'total'      => $total,
        ]);

        return new LogsResult(
            entries: $entries,
            total:   $total,
            page:    $filters->page,
            perPage: $filters->perPage,
        );
    }
}
