<?php

namespace App\Services\Payments;

final readonly class ZarinPalGatewaySettings
{
    public function __construct(
        public string $merchantId,
        public bool $sandbox,
    ) {}
}
