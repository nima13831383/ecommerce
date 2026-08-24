<?php

namespace App\Services\Payments\Data;

class PaymentInitiationResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerPaymentIdentifier = null,
        public ?string $redirectUrl = null,
        public array $metadata = [],
        public ?string $failureReason = null,
    ) {}
}
