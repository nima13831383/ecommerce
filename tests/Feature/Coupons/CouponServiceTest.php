<?php

use App\Exceptions\CouponConfigurationException;
use App\Exceptions\CouponValidationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Carbon\Carbon;

function couponTestProduct(string $suffix, int $price = 100): Product
{
    return Product::query()->create([
        'name' => 'Coupon test '.$suffix,
        'slug' => 'coupon-test-'.$suffix,
        'type' => 'simple',
        'sku' => 'COUPON-'.$suffix,
        'price' => $price,
        'status' => 'published',
    ]);
}

function couponTestCart(array $items): Cart
{
    $cart = Cart::query()->create(['token' => 'coupon-cart-'.fake()->unique()->numerify('#####')]);

    foreach ($items as [$product, $quantity, $unitPrice]) {
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);
    }

    return $cart->load('items');
}

function couponTestOrder(?int $userId = null): Order
{
    return Order::query()->create([
        'order_number' => 'CPN-'.fake()->unique()->numerify('######'),
        'user_id' => $userId,
        'customer_name' => 'Coupon test customer',
        'customer_mobile' => '09120000000',
        'currency' => 'IRR',
    ]);
}

test('fixed cart discounts are capped at the eligible amount', function (): void {
    $included = couponTestProduct('eligible', 40);
    $other = couponTestProduct('other', 60);
    $cart = couponTestCart([[$included, 1, 40], [$other, 1, 60]]);
    $coupon = Coupon::query()->create(['code' => 'fixed-cap', 'type' => 'fixed_cart', 'amount' => 50]);
    $coupon->products()->attach($included->id);

    $evaluation = app(CouponService::class)->evaluate(' fixed-cap ', $cart);

    expect($evaluation->cartAmount)->toBe(100)
        ->and($evaluation->eligibleAmount)->toBe(40)
        ->and($evaluation->discountAmount)->toBe(40)
        ->and($evaluation->finalAmount)->toBe(60);
});

test('percentage discounts use deterministic integer half-up rounding and a cap', function (): void {
    $product = couponTestProduct('percent', 101);
    $cart = couponTestCart([[$product, 1, 101]]);
    $coupon = Coupon::query()->create([
        'code' => 'percent-cap', 'type' => 'percent', 'amount' => 50, 'max_discount' => 50,
    ]);

    expect(app(CouponService::class)->evaluate('PERCENT-CAP', $cart)->discountAmount)->toBe(50);
});

test('availability boundaries, minimum and maximum spend are centralized', function (): void {
    Carbon::setTestNow('2026-08-25 12:00:00');
    $product = couponTestProduct('dates', 100);
    $cart = couponTestCart([[$product, 1, 100]]);
    $service = app(CouponService::class);

    $scheduled = Coupon::query()->create(['code' => 'scheduled', 'type' => 'fixed_cart', 'amount' => 1, 'starts_at' => now()->addSecond()]);
    expect(fn () => $service->evaluate('scheduled', $cart))->toThrow(CouponValidationException::class);

    $boundary = Coupon::query()->create(['code' => 'boundary', 'type' => 'fixed_cart', 'amount' => 1, 'starts_at' => now(), 'expires_at' => now()]);
    expect($service->evaluate('boundary', $cart)->discountAmount)->toBe(1);

    $limited = Coupon::query()->create(['code' => 'spend', 'type' => 'fixed_cart', 'amount' => 1, 'min_spend' => 101, 'max_spend' => 200]);
    expect(fn () => $service->evaluate('spend', $cart))->toThrow(CouponValidationException::class);

    Carbon::setTestNow();
});

test('product and user restrictions are applied without consuming usage', function (): void {
    $allowed = couponTestProduct('allowed', 100);
    $blocked = couponTestProduct('blocked', 100);
    $cart = couponTestCart([[$allowed, 1, 100], [$blocked, 1, 100]]);
    $user = User::factory()->create();
    $coupon = Coupon::query()->create([
        'code' => 'restricted', 'type' => 'fixed_cart', 'amount' => 20,
        'usage_limit' => 2, 'usage_limit_per_user' => 1,
    ]);
    $coupon->products()->attach($allowed->id);
    $coupon->users()->attach($user->id);

    $service = app(CouponService::class);
    expect($service->evaluate('restricted', $cart, $user->id)->eligibleAmount)->toBe(100)
        ->and($coupon->fresh()->usage_count)->toBe(0);
});

test('redemption is transactional, actor-aware, and idempotent per order', function (): void {
    $product = couponTestProduct('redeem', 100);
    $cart = couponTestCart([[$product, 1, 100]]);
    $user = User::factory()->create();
    $coupon = Coupon::query()->create(['code' => 'redeem-once', 'type' => 'fixed_cart', 'amount' => 25, 'usage_limit' => 1]);
    $order = couponTestOrder($user->id);
    $service = app(CouponService::class);

    expect($service->apply($coupon, $cart, $order, $user->id))->toBe(25)
        ->and($service->apply($coupon, $cart, $order, $user->id))->toBe(25)
        ->and(CouponUsage::query()->where('coupon_id', $coupon->id)->count())->toBe(1)
        ->and($coupon->fresh()->usage_count)->toBe(1);
});

test('invalid coupon configuration is rejected at the service boundary', function (): void {
    $coupon = new Coupon(['code' => 'bad', 'type' => 'percent', 'amount' => 101]);

    expect(fn () => app(CouponService::class)->assertValidConfiguration($coupon))
        ->toThrow(CouponConfigurationException::class);
});
it('accepts only integral finite numeric values for Coupon Rial amounts at the service boundary', function (): void {
    $service = app(CouponService::class);

    expect(fn () => $service->assertValidConfigurationData(['code' => 'INT-FLOAT', 'type' => 'fixed_cart', 'amount' => 29.0]))->not->toThrow(CouponConfigurationException::class)
        ->and(fn () => $service->assertValidConfigurationData(['code' => 'FRACTION', 'type' => 'fixed_cart', 'amount' => 29.1]))->toThrow(CouponConfigurationException::class)
        ->and(fn () => $service->assertValidConfigurationData(['code' => 'INFINITY', 'type' => 'fixed_cart', 'amount' => INF]))->toThrow(CouponConfigurationException::class);
});
