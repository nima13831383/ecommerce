<?php

namespace App\Services\Coupons;

use App\Models\Coupon;

final readonly class CouponEvaluation
{
    public function __construct(
        public Coupon $coupon,
        public int $cartAmount,
        public int $eligibleAmount,
        public int $discountAmount,
        public int $finalAmount,
    ) {}
}
