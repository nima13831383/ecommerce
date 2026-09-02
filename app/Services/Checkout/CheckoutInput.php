<?php

namespace App\Services\Checkout;

readonly class CheckoutInput
{
    public function __construct(
        public int $cartId,
        public int $shippingAddressId,
        public ?int $billingAddressId,
        public string $shippingService,
        public string $shippingPaymentType,
        public ?string $idempotencyKey = null,
        public ?string $customerNote = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
