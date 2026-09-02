<?php

use App\Exceptions\CouponConfigurationException;
use App\Exceptions\CouponValidationException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Spatie\Permission\Models\Role;

function policyCouponProduct(): Product
{
    return Product::query()->create([
        'name' => 'Coupon policy product '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => 'simple',
        'status' => 'published',
        'price' => 1000,
    ]);
}

function policyCouponCart(Product $product): Cart
{
    $cart = Cart::query()->create(['token' => fake()->unique()->uuid(), 'currency' => 'IRR', 'status' => 'active']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000, 'line_key' => "{$product->id}:simple"]);

    return $cart->fresh(['items.product', 'items.variation']);
}

it('combines explicit user and role targeting as independent dimensions', function (): void {
    $user = User::factory()->create();
    $role = Role::findOrCreate('customer', 'web');
    $user->assignRole($role);
    $coupon = Coupon::query()->create(['code' => 'ROLE-USER', 'type' => 'percent', 'amount' => 10]);
    $coupon->users()->attach($user->id, ['is_excluded' => false]);
    $coupon->roles()->attach($role->id, ['is_excluded' => false]);

    expect(app(CouponService::class)->evaluateCoupon($coupon->fresh(), policyCouponCart(policyCouponProduct()), $user->id)->discountAmount)->toBe(100);
});

it('lets an explicitly included user override an excluded role', function (): void {
    $user = User::factory()->create();
    $role = Role::findOrCreate('wholesale', 'web');
    $user->assignRole($role);
    $coupon = Coupon::query()->create(['code' => 'USER-OVERRIDE', 'type' => 'percent', 'amount' => 10]);
    $coupon->users()->attach($user->id, ['is_excluded' => false]);
    $coupon->roles()->attach($role->id, ['is_excluded' => true]);

    expect(app(CouponService::class)->evaluateCoupon($coupon->fresh(), policyCouponCart(policyCouponProduct()), $user->id)->discountAmount)->toBe(100);
});

it('lets an explicitly excluded user override an included role', function (): void {
    $user = User::factory()->create();
    $role = Role::findOrCreate('customer', 'web');
    $user->assignRole($role);
    $coupon = Coupon::query()->create(['code' => 'USER-BLOCK', 'type' => 'percent', 'amount' => 10]);
    $coupon->users()->attach($user->id, ['is_excluded' => true]);
    $coupon->roles()->attach($role->id, ['is_excluded' => false]);

    expect(fn () => app(CouponService::class)->evaluateCoupon($coupon->fresh(), policyCouponCart(policyCouponProduct()), $user->id))->toThrow(CouponValidationException::class);
});

it('keeps product restrictions independent from an explicit user override', function (): void {
    $user = User::factory()->create();
    $allowed = policyCouponProduct();
    $excluded = policyCouponProduct();
    $coupon = Coupon::query()->create(['code' => 'PRODUCT-BLOCK', 'type' => 'percent', 'amount' => 10]);
    $coupon->users()->attach($user->id, ['is_excluded' => false]);
    $coupon->products()->attach($excluded->id, ['is_excluded' => true]);

    expect(fn () => app(CouponService::class)->evaluateCoupon($coupon->fresh(), policyCouponCart($excluded), $user->id))->toThrow(CouponValidationException::class)
        ->and(app(CouponService::class)->evaluateCoupon($coupon->fresh(), policyCouponCart($allowed), $user->id)->discountAmount)->toBe(100);
});

it('rejects include and exclude values in the same coupon dimension', function (): void {
    $product = policyCouponProduct();
    $coupon = Coupon::query()->create(['code' => 'MIXED-PRODUCTS', 'type' => 'percent', 'amount' => 10]);
    $coupon->products()->attach($product->id, ['is_excluded' => false]);
    $other = policyCouponProduct();
    $coupon->products()->attach($other->id, ['is_excluded' => true]);

    expect(fn () => app(CouponService::class)->assertValidConfiguration($coupon->fresh()))->toThrow(CouponConfigurationException::class);
});
