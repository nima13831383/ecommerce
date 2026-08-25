<?php

namespace App\Services\Checkout;

use Carbon\CarbonInterface;

readonly class CheckoutInput
{
    public function __construct(
        public int $cartId,
        public int $shippingAddressId,
        public ?int $billingAddressId,
        public int $originProvinceId,
        public int $originCityId,
        public string $shippingService,
        public string $parcelType,
        public string $shippingPaymentType,
        public int $packageSizeId,
        public ?string $idempotencyKey = null,
        public ?CarbonInterface $reservationExpiresAt = null,
        public ?string $customerNote = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
