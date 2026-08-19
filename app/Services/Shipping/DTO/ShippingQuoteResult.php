<?php

namespace App\Services\Shipping\DTO;

readonly class ShippingQuoteResult
{
    /**
     * @param  array<int, array{key: string, label: string, amount: int|float}>  $breakdown
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $service,
        public ?bool $available,
        public int $total,
        public string $currency,
        public array $breakdown,
        public array $warnings = [],
        public array $metadata = [],
    ) {}
}
