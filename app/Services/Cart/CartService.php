<?php

namespace App\Services\Cart;

use App\Exceptions\CouponValidationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Tax\TaxCalculator;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CartService
{
    public function __construct(
        private readonly ProductPriceResolver $prices,
        private readonly TaxCalculator $taxes,
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
    ) {}

    public function getOrCreateForUser(int $userId): Cart
    {
        return DB::transaction(function () use ($userId): Cart {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($cart) {
                return $cart;
            }

            return Cart::query()->create([
                'user_id' => $userId,
                'token' => $this->newGuestToken(),
                'currency' => 'IRR',
                'status' => 'active',
                'last_activity_at' => now(),
            ]);
        });
    }

    public function getForUser(User $user, int $cartId): Cart
    {
        $cart = Cart::query()->whereBelongsTo($user)->whereKey($cartId)->first();

        if (! $cart) {
            throw new DomainException('The selected cart does not belong to this user.');
        }

        return $cart;
    }

    public function getOrCreateForToken(string $token): Cart
    {
        $token = trim($token);

        if ($token === '') {
            throw new DomainException('A cart token is required.');
        }

        if (strlen($token) > 64) {
            throw new DomainException('A cart token may not exceed 64 characters.');
        }

        return DB::transaction(function () use ($token): Cart {
            $cart = Cart::query()
                ->where('token', $token)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($cart) {
                return $cart;
            }

            try {
                return Cart::query()->create([
                    'token' => $token,
                    'currency' => 'IRR',
                    'status' => 'active',
                    'last_activity_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $existing = Cart::query()
                    ->where('token', $token)
                    ->where('status', 'active')
                    ->first();

                if ($existing) {
                    return $existing;
                }

                throw $exception;
            }
        });
    }

    public function newGuestToken(): string
    {
        return Str::random(64);
    }

    public function addItem(Cart $cart, Product $product, int $quantity, ?ProductVariation $variation = null): CartRecalculationResult
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($cart, $product, $quantity, $variation): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            [$product, $variation, $owner, $unitPrice] = $this->resolveSellable($product, $variation);
            $item = $this->findLockedItem($cart, $product, $variation);
            $newQuantity = $quantity + ($item?->quantity ?? 0);
            $this->assertAvailable($owner, $newQuantity);

            if (! $item) {
                $item = new CartItem;
                $item->cart_id = $cart->id;
                $item->product_id = $product->id;
                $item->product_variation_id = $variation?->id;
                $item->line_key = CartItem::makeLineKey($product->id, $variation?->id);
            }

            $item->quantity = $newQuantity;
            $item->unit_price = $unitPrice;
            $item->line_total = $this->multiply($unitPrice, $newQuantity);
            $item->save();

            return $this->recalculateLocked($cart);
        });
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): CartRecalculationResult
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($cart, $item, $quantity): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            $item = CartItem::query()
                ->whereKey($item->id)
                ->whereBelongsTo($cart)
                ->lockForUpdate()
                ->firstOrFail();
            [$product, $variation, $owner] = $this->resolveSellable($item->product, $item->variation);
            $this->assertAvailable($owner, $quantity);
            $item->quantity = $quantity;
            $item->save();

            return $this->recalculateLocked($cart);
        });
    }

    public function removeItem(Cart $cart, CartItem $item): CartRecalculationResult
    {
        return DB::transaction(function () use ($cart, $item): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            $item = CartItem::query()
                ->whereKey($item->id)
                ->whereBelongsTo($cart)
                ->lockForUpdate()
                ->firstOrFail();
            $item->delete();

            return $this->recalculateLocked($cart);
        });
    }

    public function clear(Cart $cart): CartRecalculationResult
    {
        return DB::transaction(function () use ($cart): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            $cart->items()->delete();
            $cart->forceFill([
                'coupon_id' => null,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'last_activity_at' => now(),
            ])->save();
            $cart->coupons()->detach();

            return new CartRecalculationResult($cart->load('items'));
        });
    }

    public function applyCoupon(Cart $cart, string $code, ?int $userId = null): CartRecalculationResult
    {
        return DB::transaction(function () use ($cart, $code, $userId): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            $userId ??= $cart->user_id;
            $evaluation = $this->coupons->evaluate($code, $cart->load('items'), $userId);
            $cart->forceFill(['coupon_id' => $evaluation->coupon->id])->save();
            $cart->coupons()->sync([
                $evaluation->coupon->id => [
                    'discount_amount' => $evaluation->discountAmount,
                    'sort_order' => 0,
                ],
            ]);

            return $this->recalculateLocked($cart, $userId);
        });
    }

    public function removeCoupon(Cart $cart): CartRecalculationResult
    {
        return DB::transaction(function () use ($cart): CartRecalculationResult {
            $cart = $this->lockActiveCart($cart);
            $cart->forceFill(['coupon_id' => null])->save();
            $cart->coupons()->detach();

            return $this->recalculateLocked($cart);
        });
    }

    public function recalculate(Cart $cart, ?int $userId = null): CartRecalculationResult
    {
        return DB::transaction(fn (): CartRecalculationResult => $this->recalculateLocked($this->lockActiveCart($cart), $userId));
    }

    public function convert(Cart $cart): Cart
    {
        return DB::transaction(function () use ($cart): Cart {
            $cart = $this->lockActiveCart($cart);
            $cart->forceFill([
                'status' => 'converted',
                'last_activity_at' => now(),
            ])->save();

            return $cart->fresh(['items']);
        });
    }

    private function recalculateLocked(Cart $cart, ?int $userId = null): CartRecalculationResult
    {
        $userId ??= $cart->user_id;
        $cart->load([
            'items.product' => fn ($query) => $query->withTrashed()->with('taxClass'),
            'items.variation',
        ]);
        $issues = [];
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($cart->items as $item) {
            try {
                [$product, $variation, $owner, $unitPrice] = $this->resolveSellable($item->product, $item->variation);
                $this->assertAvailable($owner, (int) $item->quantity);
                $lineSubtotal = $this->multiply($unitPrice, (int) $item->quantity);
                $taxAmount = $this->taxes->calculateTax(
                    $lineSubtotal,
                    $product->getEffectiveTaxClass(),
                    (int) $item->quantity,
                );
                $item->forceFill([
                    'unit_price' => $unitPrice,
                    'line_total' => $this->add($lineSubtotal, $taxAmount),
                ])->save();
                $subtotal = $this->add($subtotal, $lineSubtotal);
                $taxTotal = $this->add($taxTotal, $taxAmount);
            } catch (DomainException|ModelNotFoundException|InvalidArgumentException $exception) {
                $issues[] = "item:{$item->id}: {$exception->getMessage()}";
            }
        }

        $discount = 0;
        if ($cart->coupon_id !== null && $issues === []) {
            $coupon = Coupon::query()->find($cart->coupon_id);

            try {
                if (! $coupon) {
                    throw new CouponValidationException('The cart coupon no longer exists.');
                }

                $evaluation = $this->coupons->evaluateCoupon($coupon, $cart->load('items'), $userId);
                $discount = $evaluation->discountAmount;
            } catch (CouponValidationException $exception) {
                $issues[] = "coupon: {$exception->getMessage()}";
                $cart->forceFill(['coupon_id' => null])->save();
                $cart->coupons()->detach();
            }
        }

        $cart->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $taxTotal,
            'shipping_total' => 0,
            'grand_total' => $this->add($this->subtract($subtotal, $discount), $taxTotal),
            'last_activity_at' => now(),
        ])->save();

        return new CartRecalculationResult($cart->fresh()->load('items'), $issues);
    }

    /** @return array{0: Product, 1: ?ProductVariation, 2: Product|ProductVariation, 3: int} */
    private function resolveSellable(?Product $product, ?ProductVariation $variation): array
    {
        if (! $product) {
            throw new DomainException('The selected product no longer exists.');
        }

        $product = Product::query()->withTrashed()->with('taxClass')->findOrFail($product->id);

        if ($product->trashed() || $product->status !== 'published') {
            throw new DomainException('The selected product is not currently sellable.');
        }

        if ($product->type === 'simple') {
            if ($variation !== null) {
                throw new DomainException('A simple product cannot include a variation.');
            }

            return [
                $product,
                null,
                $product,
                $this->validPrice($this->prices->pricesForProduct($product)['effective_price']),
            ];
        }

        if ($product->type !== 'variable') {
            throw new DomainException('Only simple and variable products are supported by Cart.');
        }

        if ($variation === null) {
            throw new DomainException('A variable product requires a valid variation.');
        }

        $resolvedVariation = ProductVariation::query()
            ->whereKey($variation->id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        if (! $resolvedVariation) {
            throw new DomainException('The selected variation does not belong to this product.');
        }

        return [
            $product,
            $resolvedVariation,
            $resolvedVariation,
            $this->validPrice($this->prices->effectivePriceForVariation($resolvedVariation)),
        ];
    }

    private function lockActiveCart(Cart $cart): Cart
    {
        $locked = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'active') {
            throw new DomainException('Only an active Cart can be changed.');
        }

        return $locked;
    }

    private function findLockedItem(Cart $cart, Product $product, ?ProductVariation $variation): ?CartItem
    {
        return CartItem::query()
            ->whereBelongsTo($cart)
            ->where(function ($query) use ($product, $variation): void {
                $query
                    ->where('line_key', CartItem::makeLineKey($product->id, $variation?->id))
                    ->orWhere(function ($legacyQuery) use ($product, $variation): void {
                        $legacyQuery
                            ->where('product_id', $product->id)
                            ->where('product_variation_id', $variation?->id);
                    });
            })
            ->lockForUpdate()
            ->first();
    }

    private function assertAvailable(Product|ProductVariation $owner, int $quantity): void
    {
        if ($quantity > $this->inventory->availableQuantity($owner)) {
            throw new DomainException('The requested quantity is not currently available.');
        }
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new DomainException('Cart quantity must be a positive integer.');
        }
    }

    private function validPrice(?int $price): int
    {
        if ($price === null || $price < 0) {
            throw new DomainException('The selected product does not have a valid price.');
        }

        return $price;
    }

    private function multiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($right > 0 && $left > intdiv(PHP_INT_MAX, $right))) {
            throw new DomainException('Cart amount exceeds the supported range.');
        }

        return $left * $right;
    }

    private function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new DomainException('Cart amount exceeds the supported range.');
        }

        return $left + $right;
    }

    private function subtract(int $left, int $right): int
    {
        if ($right > $left) {
            throw new DomainException('Cart discount cannot exceed the subtotal.');
        }

        return $left - $right;
    }
}
