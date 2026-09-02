<?php

namespace App\Services;

use App\Exceptions\CouponConfigurationException;
use App\Exceptions\CouponValidationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Coupons\CouponEvaluation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CouponService
{
    public function __construct(private readonly ProductPriceResolver $prices) {}

    public function evaluate(string $code, Cart $cart, ?int $userId = null): CouponEvaluation
    {
        $normalizedCode = Coupon::normalizeCode($code);
        $coupon = Coupon::query()->where('code', $normalizedCode)->first();

        if (! $coupon) {
            $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [$normalizedCode])->first();
        }

        if (! $coupon) {
            throw new CouponValidationException('کد تخفیف معتبر نیست یا یافت نشد.');
        }

        return $this->evaluateCoupon($coupon, $cart, $userId);
    }

    public function validate(string $code, Cart $cart, ?int $userId = null): Coupon
    {
        return $this->evaluate($code, $cart, $userId)->coupon;
    }

    public function evaluateCoupon(Coupon $coupon, Cart $cart, ?int $userId = null): CouponEvaluation
    {
        $this->assertValidConfiguration($coupon);

        if (! $coupon->is_active) {
            throw new CouponValidationException('این کد تخفیف غیرفعال است.');
        }

        if ($coupon->starts_at?->isFuture()) {
            throw new CouponValidationException('این کد تخفیف هنوز فعال نشده است.');
        }

        if ($coupon->expires_at?->isPast()) {
            throw new CouponValidationException('تاریخ اعتبار این کد تخفیف به پایان رسیده است.');
        }

        if ($coupon->hasReachedLimit()) {
            throw new CouponValidationException('ظرفیت استفاده از این کد تخفیف تکمیل شده است.');
        }

        $user = $userId === null ? null : User::query()->find($userId);
        if ($userId !== null && $user === null) {
            throw new CouponValidationException('کاربر استفاده‌کننده یافت نشد.');
        }

        if (! $this->customerEligible($coupon, $user)) {
            throw new CouponValidationException('این کد تخفیف برای این کاربر قابل استفاده نیست.');
        }

        if ($userId !== null && $coupon->hasUserReachedLimit($userId)) {
            throw new CouponValidationException('حداکثر دفعات استفاده شما از این کد تخفیف تکمیل شده است.');
        }

        $cartAmount = $this->rawTotal($cart->items);
        if ($coupon->min_spend !== null && $cartAmount < (int) $coupon->min_spend) {
            throw new CouponValidationException(sprintf('حداقل مبلغ کالاهای سبد برای این کد تخفیف %s ریال است.', number_format((int) $coupon->min_spend)));
        }

        if ($coupon->max_spend !== null && $cartAmount > (int) $coupon->max_spend) {
            throw new CouponValidationException(sprintf('این کد تخفیف فقط برای سبدهای تا مبلغ %s ریال قابل استفاده است.', number_format((int) $coupon->max_spend)));
        }

        $eligibleItems = $this->eligibleItems($coupon, $cart);
        if ($eligibleItems->isEmpty()) {
            throw new CouponValidationException('هیچ‌یک از کالاهای سبد مشمول این کد تخفیف نیستند.');
        }

        $eligibleAmount = $this->rawTotal($eligibleItems);
        $discountAmount = $this->calculateDiscountForItems($coupon, $eligibleItems, $eligibleAmount);

        return new CouponEvaluation(
            coupon: $coupon,
            cartAmount: $cartAmount,
            eligibleAmount: $eligibleAmount,
            discountAmount: $discountAmount,
            finalAmount: $cartAmount - $discountAmount,
        );
    }

    public function calculateDiscount(Coupon $coupon, Cart $cart): int
    {
        $this->assertValidConfiguration($coupon);
        $eligibleItems = $this->eligibleItems($coupon, $cart);

        return $this->calculateDiscountForItems($coupon, $eligibleItems, $this->rawTotal($eligibleItems));
    }

    public function apply(Coupon $coupon, Cart $cart, Order $order, ?int $userId = null): int
    {
        $userId ??= $order->user_id;

        return DB::transaction(function () use ($coupon, $cart, $order, $userId): int {
            $lockedCoupon = Coupon::query()->lockForUpdate()->find($coupon->getKey());
            if (! $lockedCoupon) {
                throw new ModelNotFoundException('Coupon not found.');
            }

            $existingUsage = CouponUsage::query()
                ->where('coupon_id', $lockedCoupon->getKey())
                ->where('order_id', $order->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingUsage) {
                return (int) $existingUsage->discount_amount;
            }

            $evaluation = $this->evaluateCoupon($lockedCoupon, $cart, $userId);

            CouponUsage::query()->create([
                'coupon_id' => $lockedCoupon->getKey(),
                'user_id' => $userId,
                'order_id' => $order->getKey(),
                'discount_amount' => $evaluation->discountAmount,
            ]);

            $lockedCoupon->increment('usage_count');

            return $evaluation->discountAmount;
        });
    }

    public function reverse(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $usage = CouponUsage::query()->where('order_id', $order->getKey())->lockForUpdate()->first();
            if (! $usage) {
                return;
            }

            $coupon = Coupon::query()->lockForUpdate()->find($usage->coupon_id);
            $usage->delete();

            if ($coupon) {
                $coupon->newQuery()->whereKey($coupon->getKey())->where('usage_count', '>', 0)->decrement('usage_count');
            }
        });
    }

    public function assertValidConfiguration(Coupon $coupon): void
    {
        $this->assertValidConfigurationData([
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => $coupon->amount,
            'min_spend' => $coupon->min_spend,
            'max_spend' => $coupon->max_spend,
            'max_discount' => $coupon->max_discount,
            'usage_limit' => $coupon->usage_limit,
            'usage_limit_per_user' => $coupon->usage_limit_per_user,
            'usage_count' => $coupon->usage_count,
            'starts_at' => $coupon->starts_at,
            'expires_at' => $coupon->expires_at,
        ]);

        foreach ([
            [$coupon->includedProducts(), $coupon->excludedProducts(), 'محصول'],
            [$coupon->includedUsers(), $coupon->excludedUsers(), 'کاربر'],
            [$coupon->includedRoles(), $coupon->excludedRoles(), 'نقش کاربری'],
        ] as [$included, $excluded, $label]) {
            if ($included->exists() && $excluded->exists()) {
                throw new CouponConfigurationException("فهرست شامل و مستثنی {$label} نمی‌توانند هم‌زمان تنظیم شوند.");
            }
        }
    }

    /** @param  array<int, int|string>  $recordIds */
    public function assertTargetingMutation(Coupon $coupon, string $dimension, bool $isExcluded, array $recordIds): void
    {
        $coupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->getKey());
        $recordIds = collect($recordIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($recordIds->isEmpty()) {
            return;
        }

        [$included, $excluded, $label] = match ($dimension) {
            'products' => [$coupon->includedProducts(), $coupon->excludedProducts(), 'محصول'],
            'users' => [$coupon->includedUsers(), $coupon->excludedUsers(), 'کاربر'],
            'roles' => [$coupon->includedRoles(), $coupon->excludedRoles(), 'نقش کاربری'],
            default => throw new CouponConfigurationException('نوع محدودیت کد تخفیف معتبر نیست.'),
        };

        $includedIds = $included->lockForUpdate()->allRelatedIds()->map(fn ($id): int => (int) $id);
        $excludedIds = $excluded->lockForUpdate()->allRelatedIds()->map(fn ($id): int => (int) $id);

        $includedIds = $includedIds->diff($recordIds);
        $excludedIds = $excludedIds->diff($recordIds);

        if ($isExcluded) {
            $excludedIds = $excludedIds->merge($recordIds)->unique();
        } else {
            $includedIds = $includedIds->merge($recordIds)->unique();
        }

        if ($includedIds->isNotEmpty() && $excludedIds->isNotEmpty()) {
            throw new CouponConfigurationException("فهرست شامل و مستثنی {$label} نمی‌توانند هم‌زمان تنظیم شوند.");
        }
    }

    public function assertValidConfigurationData(array $data): void
    {
        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, ['percent', 'fixed_cart', 'fixed_product'], true)) {
            throw new CouponConfigurationException('نوع کد تخفیف معتبر نیست.');
        }

        if (blank(Coupon::normalizeCode((string) ($data['code'] ?? '')))) {
            throw new CouponConfigurationException('کد تخفیف الزامی است.');
        }

        $amount = $this->integerValue($data['amount'] ?? null, 'مقدار تخفیف');
        if ($amount < 1 || ($type === 'percent' && $amount > 100)) {
            throw new CouponConfigurationException($type === 'percent' ? 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.' : 'مبلغ تخفیف باید حداقل یک ریال باشد.');
        }

        $minSpend = $this->nullableInteger($data['min_spend'] ?? null, 'حداقل مبلغ سفارش');
        $maxSpend = $this->nullableInteger($data['max_spend'] ?? null, 'حداکثر مبلغ سفارش');
        $maxDiscount = $this->nullableInteger($data['max_discount'] ?? null, 'سقف تخفیف');

        if ($minSpend !== null && $maxSpend !== null && $minSpend > $maxSpend) {
            throw new CouponConfigurationException('حداقل مبلغ سفارش نمی‌تواند بیشتر از حداکثر مبلغ سفارش باشد.');
        }

        if ($type !== 'percent' && $maxDiscount !== null) {
            throw new CouponConfigurationException('سقف تخفیف فقط برای کدهای درصدی قابل استفاده است.');
        }

        if ($maxDiscount !== null && $maxDiscount < 1) {
            throw new CouponConfigurationException('سقف تخفیف باید مثبت باشد.');
        }

        foreach (['usage_limit' => 'محدودیت استفاده', 'usage_limit_per_user' => 'محدودیت استفاده هر کاربر'] as $field => $label) {
            $value = $this->nullableInteger($data[$field] ?? null, $label);
            if ($value !== null && $value < 1) {
                throw new CouponConfigurationException("{$label} باید حداقل یک باشد.");
            }
        }

        $usageCount = $this->integerValue($data['usage_count'] ?? 0, 'تعداد استفاده');
        if ($usageCount < 0) {
            throw new CouponConfigurationException('تعداد استفاده نمی‌تواند منفی باشد.');
        }

        $usageLimit = $this->nullableInteger($data['usage_limit'] ?? null, 'محدودیت استفاده');
        if ($usageLimit !== null && $usageCount > $usageLimit) {
            throw new CouponConfigurationException('تعداد استفاده نمی‌تواند از محدودیت کل بیشتر باشد.');
        }

        $startsAt = $data['starts_at'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;
        if ($startsAt !== null && $expiresAt !== null) {
            try {
                $invalidDateRange = Carbon::parse($startsAt)->gt(Carbon::parse($expiresAt));
            } catch (Throwable) {
                throw new CouponConfigurationException('تاریخ‌های اعتبار کد تخفیف معتبر نیستند.');
            }

            if ($invalidDateRange) {
                throw new CouponConfigurationException('تاریخ شروع نمی‌تواند بعد از تاریخ انقضا باشد.');
            }
        }
    }

    protected function eligibleItems(Coupon $coupon, Cart $cart): Collection
    {
        $cart->items->loadMissing(['product', 'variation']);
        $excludedProductIds = $coupon->excludedProducts()->pluck('products.id');
        $includedProductIds = $coupon->includedProducts()->pluck('products.id');
        $hasIncludeRules = $includedProductIds->isNotEmpty();

        return $cart->items->filter(function (CartItem $item) use ($coupon, $excludedProductIds, $includedProductIds, $hasIncludeRules): bool {
            $product = $item->product;
            if (! $product) {
                return false;
            }

            $pricing = $item->variation instanceof ProductVariation
                ? $this->prices->pricesForVariation($item->variation)
                : $this->prices->pricesForProduct($product);
            if ($coupon->exclude_discounted_products && ($pricing['is_discounted'] ?? false)) {
                return false;
            }

            if ($excludedProductIds->contains($product->id)) {
                return false;
            }

            if (! $hasIncludeRules) {
                return true;
            }

            return $includedProductIds->contains($product->id);
        })->values();
    }

    private function appliesToRoles(Coupon $coupon, ?User $user): bool
    {
        $included = $coupon->includedRoles()->pluck('roles.id');
        $excluded = $coupon->excludedRoles()->pluck('roles.id');
        if ($included->isEmpty() && $excluded->isEmpty()) {
            return true;
        }

        if ($user === null || $excluded->intersect($user->roles()->pluck('roles.id'))->isNotEmpty()) {
            return false;
        }

        return $included->isEmpty() || $included->intersect($user->roles()->pluck('roles.id'))->isNotEmpty();
    }

    private function customerEligible(Coupon $coupon, ?User $user): bool
    {
        if ($user !== null && $coupon->excludedUsers()->whereKey($user->getKey())->exists()) {
            return false;
        }

        if ($user !== null && $coupon->includedUsers()->whereKey($user->getKey())->exists()) {
            return true;
        }

        return $coupon->appliesToUser($user) && $this->appliesToRoles($coupon, $user);
    }

    private function calculateDiscountForItems(Coupon $coupon, Collection $items, int $eligibleAmount): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $discount = match ($coupon->type) {
            'percent' => $this->percentageDiscount($eligibleAmount, (int) $coupon->amount),
            'fixed_cart' => min((int) $coupon->amount, $eligibleAmount),
            'fixed_product' => $this->fixedProductDiscount($coupon, $items),
        };

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (int) $coupon->max_discount);
        }

        return min($discount, $eligibleAmount);
    }

    private function percentageDiscount(int $eligibleAmount, int $percentage): int
    {
        if ($percentage !== 0 && $eligibleAmount > intdiv(PHP_INT_MAX, $percentage)) {
            throw new CouponValidationException('مبلغ محاسبه تخفیف خارج از محدوده پشتیبانی‌شده است.');
        }

        return intdiv(($eligibleAmount * $percentage) + 50, 100);
    }

    private function fixedProductDiscount(Coupon $coupon, Collection $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $perUnit = min((int) $coupon->amount, (int) $item->unit_price);
            if ($item->quantity > 0 && $perUnit > intdiv(PHP_INT_MAX, (int) $item->quantity)) {
                throw new CouponValidationException('مبلغ محاسبه تخفیف خارج از محدوده پشتیبانی‌شده است.');
            }

            $lineDiscount = $perUnit * (int) $item->quantity;
            if ($lineDiscount > PHP_INT_MAX - $total) {
                throw new CouponValidationException('مبلغ محاسبه تخفیف خارج از محدوده پشتیبانی‌شده است.');
            }

            $total += $lineDiscount;
        }

        return $total;
    }

    private function rawTotal(Collection $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $unitPrice = (int) $item->unit_price;
            $quantity = (int) $item->quantity;
            if ($unitPrice < 0 || $quantity < 0 || ($quantity !== 0 && $unitPrice > intdiv(PHP_INT_MAX, $quantity))) {
                throw new CouponValidationException('مبلغ یا تعداد کالا برای محاسبه تخفیف معتبر نیست.');
            }

            $lineTotal = $unitPrice * $quantity;
            if ($lineTotal > PHP_INT_MAX - $total) {
                throw new CouponValidationException('مجموع مبلغ سبد خارج از محدوده پشتیبانی‌شده است.');
            }

            $total += $lineTotal;
        }

        return $total;
    }

    private function integerValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value || $value < PHP_INT_MIN || $value > PHP_INT_MAX) {
                throw new CouponConfigurationException("{$label} باید یک عدد صحیح ریالی باشد.");
            }

            return (int) $value;
        }

        if (! is_string($value) || ! preg_match('/^\d+$/', trim($value))) {
            throw new CouponConfigurationException("{$label} باید یک عدد صحیح ریالی باشد.");
        }

        $normalized = ltrim(trim($value), '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        if (strlen($normalized) > strlen((string) PHP_INT_MAX)) {
            throw new CouponConfigurationException("{$label} خارج از محدوده پشتیبانی‌شده است.");
        }

        $integer = (int) $value;
        if ((string) $integer !== $normalized) {
            throw new CouponConfigurationException("{$label} خارج از محدوده پشتیبانی‌شده است.");
        }

        return $integer;
    }

    private function nullableInteger(mixed $value, string $label): ?int
    {
        return blank($value) ? null : $this->integerValue($value, $label);
    }
}
