<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\HttpDeliveryPort;
use App\Application\Value\DeliveryResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SymfonyHttpDeliveryAdapter implements HttpDeliveryPort
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {}

    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): DeliveryResult
    {
        $start = hrtime(true);

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'body'    => $body,
                'timeout' => $timeoutSeconds,
            ]);

            $statusCode   = $response->getStatusCode();
            $responseBody = mb_substr($response->getContent(false), 0, 500);
            $durationMs   = (int) ((hrtime(true) - $start) / 1_000_000);
            $success      = $statusCode >= 200 && $statusCode < 300;

            return new DeliveryResult($statusCode, $responseBody, $durationMs, $success);
        } catch (TransportExceptionInterface $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return new DeliveryResult(null, '', $durationMs, false, $e);
        }
    }
}
