<?php

namespace App\Services\Orders;

use DomainException;

readonly class OrderPricing
{
    /** @param array<string, mixed>|null $couponSnapshot @param array<string, mixed>|null $shippingSnapshot */
    public function __construct(
        public int $discountTotal = 0,
        public int $shippingTotal = 0,
        public ?int $couponId = null,
        public ?array $couponSnapshot = null,
        public ?array $shippingSnapshot = null,
    ) {
        if ($discountTotal < 0 || $shippingTotal < 0) {
            throw new DomainException('Order pricing amounts must not be negative.');
        }
    }
}
