<?php

namespace App\Services\Orders;

use DomainException;

readonly class OrderCreationContext
{
    public function __construct(
        public ?OrderPricing $pricing = null,
        public ?string $idempotencyKey = null,
        public ?string $requestFingerprint = null,
    ) {
        if (($idempotencyKey === null) !== ($requestFingerprint === null)) {
            throw new DomainException('Order idempotency key and fingerprint must be provided together.');
        }
    }
}
