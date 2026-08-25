<?php

namespace App\Services\Cart;

use App\Models\Cart;

final readonly class CartRecalculationResult
{
    /** @param array<int, string> $issues */
    public function __construct(
        public Cart $cart,
        public array $issues = [],
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
