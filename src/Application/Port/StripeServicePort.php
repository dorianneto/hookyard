<?php

declare(strict_types=1);

namespace App\Application\Port;

interface StripeServicePort
{
    public function createCheckoutSession(
        string $stripePriceId,
        string $userId,
        string $planId,
        string $successUrl,
        string $cancelUrl,
    ): string;
}
