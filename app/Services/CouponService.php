<?php

namespace App\Services;

use App\Exceptions\CouponValidationException;
use App\Models\{Cart, CartItem, Coupon, CouponUsage, Order};
use Illuminate\Support\Collection;

class CouponService
{
    /**
     * اعتبارسنجی کامل کوپن روی سبد خرید.
     * در صورت خطا CouponValidationException پرتاب می‌کند.
     * در صورت موفقیت، شیء Coupon را برمی‌گرداند.
     */
    public function validate(string $code, Cart $cart, ?int $userId = null): Coupon
    {
        //۱. وجود و فعال‌بودن کوپن
        $coupon = Coupon::usable()->where('code', $code)->first();
        if (! $coupon) {
            throw new CouponValidationException('کد تخفیف معتبر نیست یا منقضی شده است.');
        }

        // ۲. سقف کلی استفاده
        if ($coupon->hasReachedLimit()) {
            throw new CouponValidationException('ظرفیت استفاده از این کوپن تمام شده است.');
        }

        // ۳. محدودیت per-user
        if ($userId && $coupon->usage_limit_per_user !== null) {
            if ($coupon->userUsageCount($userId) >= $coupon->usage_limit_per_user) {
                throw new CouponValidationException('شما قبلاً از حداکثر دفعات مجاز این کوپن استفاده کرده‌اید.');
            }
        }

        $cartTotal = $this->rawTotal($cart->items);

        // ۴. حداقل مبلغ سبد
        if ($coupon->min_spend !== null && $cartTotal < (int) $coupon->min_spend) {
            throw new CouponValidationException(
                sprintf('حداقل مبلغ سبد برای این کوپن %s تومان است.', number_format($coupon->min_spend))
            );
        }

        // ۵. حداکثر مبلغ سبد
        if ($coupon->max_spend !== null && $cartTotal > (int) $coupon->max_spend) {
            throw new CouponValidationException(
                sprintf('این کوپن فقط برای سبدهای زیر %s تومان قابل استفاده است.', number_format($coupon->max_spend))
            );
        }

        // ۶. وجود آیتم واجد شرایط
        if ($this->eligibleItems($coupon, $cart)->isEmpty()) {
            throw new CouponValidationException('هیچ‌کدام از محصولات سبد شما شامل این کوپن نمی‌شوند.');
        }

        return $coupon;
    }

    /**
     * محاسبه مبلغ تخفیف بدون اعمال (برای preview یا نمایش زنده).
     */
    public function calculateDiscount(Coupon $coupon, Cart $cart): int
    {
        $eligible      = $this->eligibleItems($coupon, $cart);
        $eligibleTotal = $this->rawTotal($eligible);
        $cartTotal     = $this->rawTotal($cart->items);

        $discount = match ($coupon->type) {
            'percent'        => $this->calcPercent($coupon, $eligibleTotal),
            'fixed_cart'     => (int) $coupon->amount,
            'fixed_product'  => $this->calcFixedProduct($coupon, $eligible),
        };

        // هرگز بیشتر از مجموع سبد نشود
        return min($discount, $cartTotal);
    }

    /**
     * اعمال رسمی کوپن (پس از ایجاد Order).
     * لاگ در coupon_usages ثبت و usage_count افزایش می‌یابد.
     */
    public function apply(Coupon $coupon, Cart $cart, Order $order, ?int $userId = null): int
    {
        $discountAmount = $this->calculateDiscount($coupon, $cart);

        CouponUsage::create([
            'coupon_id'       => $coupon->id,
            'user_id'         => $userId,
            'order_id'        => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        // increment برای جلوگیری از race condition بهتر از update مستقیم است
        $coupon->increment('usage_count');

        return $discountAmount;
    }

    /**
     * برگشت کوپن هنگام لغو/بازپرداخت سفارش.
     */
    public function reverse(Order $order): void
    {
        $usage = CouponUsage::where('order_id', $order->id)->first();
        if (! $usage) {
            return;
        }

        $coupon = $usage->coupon;
        $usage->delete();

        // usage_count هرگز زیر صفر نرود
        if ($coupon->usage_count > 0) {
            $coupon->decrement('usage_count');
        }
    }

    // ──────────────── محاسبات ────────────────

    protected function calcPercent(Coupon $coupon, int $eligibleTotal): int
    {
        $discount = (int) round($eligibleTotal * $coupon->amount / 100);
        if ($coupon->max_discount !== null) {
            $discount = min($discount, (int) $coupon->max_discount);
        }
        return $discount;
    }

    protected function calcFixedProduct(Coupon $coupon, Collection $items): int
    {
        // هر آیتم حداکثر به اندازه قیمت خودش تخفیف می‌گیرد (منفی نشود)
        return (int) $items->sum(
            fn(CartItem $item) => min((int) $coupon->amount, (int) $item->unit_price) * $item->quantity
        );
    }

    // ──────────────── فیلتر شمول/استثنا ────────────────

    /**
     * آیتم‌های واجد شرایط کوپن را بر اساس قوانین include/exclude برمی‌گرداند.
     *
     * منطق (مشابه WooCommerce):
     *- ابتدا excluded_products و excluded_categories حذف می‌شوند.
     *  - اگر included_products یا included_categories تعریف شده باشند،
     *    فقط آیتم‌هایی که در حداقل یکی از آن‌ها هستند نگه داشته می‌شوند.
     *  - اگر هیچ لیست شمولی وجود نداشته باشد، همه باقی‌مانده‌ها واجد شرایطند.
     */
    protected function eligibleItems(Coupon $coupon, Cart $cart): Collection
    {
        $cart->items->loadMissing('product.categories');

        // بارگذاری یک‌بار تمام ID های include/exclude
        $excludedProductIds  = $coupon->excludedProducts()->pluck('products.id');
        $excludedCategoryIds = $coupon->excludedCategories()->pluck('categories.id');
        $includedProductIds  = $coupon->includedProducts()->pluck('products.id');
        $includedCategoryIds = $coupon->includedCategories()->pluck('categories.id');

        $hasIncludeRules = $includedProductIds->isNotEmpty() || $includedCategoryIds->isNotEmpty();

        return $cart->items->filter(function (CartItem $item) use (
            $coupon,
            $excludedProductIds,
            $excludedCategoryIds,
            $includedProductIds,
            $includedCategoryIds,
            $hasIncludeRules
        ) {
            $product    = $item->product;
            $pid        = $product->id;
            $catIds     = $product->categories->pluck('id');

            // استثناء: محصول sale
            if ($coupon->exclude_sale_items && $product->on_sale) {
                return false;
            }

            // استثناء: محصول مستقیم
            if ($excludedProductIds->contains($pid)) {
                return false;
            }

            // استثناء: دسته
            if ($catIds->intersect($excludedCategoryIds)->isNotEmpty()) {
                return false;
            }

            // اگر قانون شمول وجود دارد، حداقل یکی باید صادق باشد
            if ($hasIncludeRules) {
                $inProduct = $includedProductIds->contains($pid);
                $inCategory = $catIds->intersect($includedCategoryIds)->isNotEmpty();
                return $inProduct || $inCategory;
            }

            return true;
        });
    }

    protected function rawTotal(Collection $items): int
    {
        return (int) $items->sum(fn(CartItem $i) => (int) $i->unit_price * $i->quantity);
    }
}
