<?php

namespace App\Services\Payments\Data;

class PaymentVerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $providerReference = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public array $metadata = [],
        public ?string $failureReason = null,
    ) {}
}
