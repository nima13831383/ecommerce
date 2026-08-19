<?php

namespace App\Services\Shipping\DTO;

readonly class ShippingQuoteInput
{
    public function __construct(
        public int $originProvinceId,
        public int $originCityId,
        public int $destinationProvinceId,
        public int $destinationCityId,
        public float $weightGrams,
        public int $declaredValueRials,
        public string $parcelType,
        public string $paymentType,
        public int $packageSizeId,
        public string $service,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            originProvinceId: (int) $data['origin_province'],
            originCityId: (int) $data['origin_city'],
            destinationProvinceId: (int) $data['destination_province'],
            destinationCityId: (int) $data['destination_city'],
            weightGrams: (float) $data['weight'],
            declaredValueRials: (int) $data['declared_value'],
            parcelType: (string) $data['parcel_type'],
            paymentType: (string) $data['payment_type'],
            packageSizeId: (int) $data['package_size'],
            service: (string) $data['service'],
        );
    }
}
