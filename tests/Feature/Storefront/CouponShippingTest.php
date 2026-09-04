<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;

function couponShippingProduct(string $slug, int $price = 1000, array $attributes = []): Product
{
    $product = Product::query()->create(array_merge([
        'name' => $slug,
        'slug' => $slug,
        'type' => 'simple',
        'status' => 'published',
        'price' => $price,
        'weight' => 1,
        'volume' => 10,
        'parcel_type' => 'normal',
    ], $attributes));

    app(InventoryService::class)->setOnHand($product, 20);

    return $product;
}

function couponShippingAddress(User $user, array $overrides = []): Address
{
    return app(AddressService::class)->create($user, array_merge([
        'type' => 'shipping',
        'first_name' => 'Customer',
        'last_name' => 'Test',
        'mobile' => '09120000000',
        'province_id' => 1,
        'city_id' => 1,
        'postal_code' => '0123456789',
        'address_line' => 'Main street',
        'is_default' => false,
    ], $overrides));
}

function storefrontCartFor(User $user, Product $product, int $quantity = 1): Cart
{
    $cart = app(CartService::class)->getOrCreateForUser($user->id);
    app(CartService::class)->addItem($cart, $product, $quantity);

    return $cart->fresh(['items']);
}

test('cart coupon web adapter applies supported types and does not redeem usage', function (): void {
    $user = User::factory()->create();
    $product = couponShippingProduct('coupon-web-product', 1000);
    $this->actingAs($user);
    storefrontCartFor($user, $product, 2);

    foreach ([
        ['code' => 'WEBPERCENT', 'type' => 'percent', 'amount' => 10, 'discount' => 200],
        ['code' => 'WEBFIXEDCART', 'type' => 'fixed_cart', 'amount' => 150, 'discount' => 150],
        ['code' => 'WEBFIXEDPRODUCT', 'type' => 'fixed_product', 'amount' => 100, 'discount' => 200],
    ] as $fixture) {
        $coupon = Coupon::query()->create([
            'code' => $fixture['code'],
            'type' => $fixture['type'],
            'amount' => $fixture['amount'],
        ]);

        $this->post(route('storefront.cart.coupon.apply'), [
            '_token' => csrf_token(),
            'coupon' => $coupon->code,
        ])->assertRedirect(route('storefront.cart.show'))->assertSessionHas('status');

        $cart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail()->fresh();
        expect($cart->discount_total)->toBe($fixture['discount'])
            ->and(CouponUsage::query()->where('coupon_id', $coupon->id)->count())->toBe(0)
            ->and(Order::query()->count())->toBe(0)
            ->and(InventoryReservation::query()->count())->toBe(0);

        $this->delete(route('storefront.cart.coupon.remove'), ['_token' => csrf_token()])
            ->assertRedirect(route('storefront.cart.show'));
    }
});

test('invalid or expired coupon is rejected with safe web validation and no mutation', function (): void {
    $user = User::factory()->create();
    $product = couponShippingProduct('coupon-invalid-product');
    $this->actingAs($user);
    storefrontCartFor($user, $product);
    Coupon::query()->create(['code' => 'EXPIREDWEB', 'type' => 'percent', 'amount' => 10, 'expires_at' => now()->subDay()]);

    $this->from(route('storefront.cart.show'))
        ->post(route('storefront.cart.coupon.apply'), ['_token' => csrf_token(), 'coupon' => 'EXPIREDWEB'])
        ->assertRedirect(route('storefront.cart.show'))
        ->assertSessionHasErrors('coupon')
        ->assertSessionMissing('exception');

    expect(Cart::query()->where('user_id', $user->id)->firstOrFail()->coupon_id)->toBeNull();
});

test('web coupon application preserves product and sale targeting rules during recalculation', function (): void {
    $user = User::factory()->create();
    $product = couponShippingProduct('coupon-targeted-product', 1000, ['sale_price' => 800]);
    $this->actingAs($user);
    storefrontCartFor($user, $product);
    $coupon = Coupon::query()->create([
        'code' => 'SALE-BLOCK-WEB',
        'type' => 'percent',
        'amount' => 10,
        'exclude_discounted_products' => true,
    ]);
    $coupon->users()->attach($user->id, ['is_excluded' => false]);
    $coupon->products()->attach($product->id, ['is_excluded' => false]);

    $this->post(route('storefront.cart.coupon.apply'), [
        '_token' => csrf_token(),
        'coupon' => $coupon->code,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('coupon');

    expect(Cart::query()->where('user_id', $user->id)->firstOrFail()->coupon_id)->toBeNull();
});

test('shipping quote uses owned address and fixed/free server modes without side effects', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = couponShippingProduct('shipping-web-product', 2000);
    $address = couponShippingAddress($user);
    $otherAddress = couponShippingAddress($other);
    $this->actingAs($user);
    storefrontCartFor($user, $product);
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'fixed');
    $settings->update('shipping.fixed_rate_amount', 250);

    $this->post(route('storefront.cart.shipping.quote'), [
        '_token' => csrf_token(),
        'address_id' => $address->id,
        'service' => 'pishtaz',
        'payment_type' => 'online',
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHas('status');

    $this->get(route('storefront.cart.show'))
        ->assertOk()
        ->assertSee('250')
        ->assertSee('shipping-quote-result', false);

    $settings->update('shipping.mode', 'free');
    $this->get(route('storefront.cart.show'))->assertOk()->assertSee('shipping-quote-result', false);
    expect(session('storefront_shipping_selection.address_id'))->toBe($address->id)
        ->and(Order::query()->count())->toBe(0)
        ->and(InventoryReservation::query()->count())->toBe(0)
        ->and(CouponUsage::query()->count())->toBe(0);

    $this->post(route('storefront.cart.shipping.quote'), [
        '_token' => csrf_token(),
        'address_id' => $otherAddress->id,
        'service' => 'pishtaz',
        'payment_type' => 'online',
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('shipping');
});

test('shipping quote ignores caller supplied authoritative fields and validates inputs', function (): void {
    $user = User::factory()->create();
    $product = couponShippingProduct('shipping-validation-product');
    $address = couponShippingAddress($user);
    $this->actingAs($user);
    storefrontCartFor($user, $product);
    app(SettingsService::class)->update('shipping.mode', 'fixed');
    app(SettingsService::class)->update('shipping.fixed_rate_amount', 400);

    $this->post(route('storefront.cart.shipping.quote'), [
        '_token' => csrf_token(),
        'address_id' => $address->id,
        'service' => 'invalid',
        'payment_type' => 'online',
        'price' => 1,
        'weight' => 1,
        'package_id' => 999,
    ])->assertSessionHasErrors('service');

    $this->post(route('storefront.cart.shipping.quote'), [
        '_token' => csrf_token(),
        'address_id' => $address->id,
        'service' => 'pishtaz',
        'payment_type' => 'online',
        'price' => 1,
        'weight' => 1,
        'package_id' => 999,
    ])->assertRedirect(route('storefront.cart.show'));

    expect(session('storefront_shipping_selection'))->toMatchArray(['address_id' => $address->id, 'service' => 'pishtaz', 'payment_type' => 'online']);
});

test('calculator quote derives weight, fragile parcel state, origin, and package on the server', function (): void {
    $user = User::factory()->create();
    $product = couponShippingProduct('shipping-calculator-product', 5000, [
        'weight' => 1.1,
        'volume' => 20,
        'parcel_type' => 'fragile',
    ]);
    $address = couponShippingAddress($user, ['province_id' => 27, 'city_id' => 6971]);
    $this->actingAs($user);
    storefrontCartFor($user, $product, 1);
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'calculator');
    $settings->update('shipping.origin_province_id', 2);
    $settings->update('shipping.origin_city_id', 4391);
    $settings->update('shipping.packages', [
        ['id' => 'small', 'name' => 'Small', 'capacity_volume' => 20, 'max_weight' => 2000, 'code' => 1, 'active' => true],
    ]);

    $this->post(route('storefront.cart.shipping.quote'), [
        '_token' => csrf_token(),
        'address_id' => $address->id,
        'service' => 'vijeh',
        'payment_type' => 'online',
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHas('status');

    $this->get(route('storefront.cart.show'))->assertOk()->assertSee('shipping-quote-result', false);
    expect(session('storefront_shipping_selection.service'))->toBe('vijeh')
        ->and(Order::query()->count())->toBe(0)
        ->and(InventoryReservation::query()->count())->toBe(0);
});
