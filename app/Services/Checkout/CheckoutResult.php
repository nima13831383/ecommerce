<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Services\Shipping\DTO\ShippingQuoteResult;

readonly class CheckoutResult
{
    /** @param array<int, string> $issues */
    public function __construct(
        public Cart $cart,
        public ?Order $order,
        public ?ShippingQuoteResult $shippingQuote,
        public int $subtotal,
        public int $discountTotal,
        public int $taxTotal,
        public int $shippingTotal,
        public int $grandTotal,
        public array $issues = [],
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
