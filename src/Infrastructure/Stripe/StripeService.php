<?php

declare(strict_types=1);

namespace App\Infrastructure\Stripe;

use App\Application\Port\StripeServicePort;
use Stripe\StripeClient;

final class StripeService implements StripeServicePort
{
    private StripeClient $client;

    public function __construct(string $secretKey)
    {
        $this->client = new StripeClient($secretKey);
    }

    public function createCheckoutSession(
        string $stripePriceId,
        string $userId,
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $session = $this->client->checkout->sessions->create([
            'mode'        => 'subscription',
            'line_items'  => [['price' => $stripePriceId, 'quantity' => 1]],
            'metadata'    => ['user_id' => $userId, 'plan_id' => $planId],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
        ]);

        return $session->url;
    }
}
