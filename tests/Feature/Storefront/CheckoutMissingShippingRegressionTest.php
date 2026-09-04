<?php

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;

function checkoutBugProduct(string $suffix = 'default'): Product
{
    $product = Product::query()->create([
        'name' => "Checkout bug {$suffix}",
        'slug' => "checkout-bug-{$suffix}",
        'sku' => "CHECKOUT-BUG-{$suffix}",
        'type' => 'simple',
        'price' => 125_000,
        'weight' => 0.5,
        'volume' => 100,
        'status' => 'published',
    ]);

    app(InventoryService::class)->setOnHand($product, 5, reason: 'Checkout shipping regression setup');

    return $product;
}

function checkoutBugAddress(User $user): Address
{
    return app(AddressService::class)->create($user, [
        'type' => AddressType::Both->value,
        'first_name' => 'Checkout',
        'last_name' => 'Regression',
        'mobile' => '09120000000',
        'province_id' => 2,
        'city_id' => 4391,
        'postal_code' => '1234567890',
        'address_line' => 'نشانی تستی تسویه حساب',
        'is_default' => true,
    ]);
}

function checkoutBugCart(User $user, Product $product): Cart
{
    $cart = app(CartService::class)->getOrCreateForUser($user->id);
    app(CartService::class)->addItem($cart, $product, 1);

    return $cart->fresh('items');
}

function checkoutBugPayload(Address $address): array
{
    return [
        'shipping_address_id' => $address->id,
        'shipping_service' => 'pishtaz',
        'shipping_payment_type' => 'online',
        'idempotency_key' => 'checkout-shipping-regression-key',
    ];
}

function configureCheckoutBugShipping(): void
{
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'calculator');
    $settings->update('shipping.origin_province_id', 2);
    $settings->update('shipping.origin_city_id', 4391);
    $settings->update('shipping.packages', [
        ['id' => 'regression-default', 'name' => 'Regression default', 'capacity_volume' => 1000, 'max_weight' => 30_000, 'code' => 1, 'active' => true],
    ]);
}

test('checkout preview reports shipping configuration separately and succeeds once configured', function (): void {
    $user = User::factory()->create();
    $product = checkoutBugProduct('configured');
    checkoutBugCart($user, $product);
    $address = checkoutBugAddress($user);

    Setting::query()->where('group', 'shipping')->delete();

    $missing = $this->actingAs($user)->get(route('storefront.checkout.show'));
    $missing->assertOk()
        ->assertSee('محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.')
        ->assertDontSee('اطلاعات ارسال یا سبد خرید برای ثبت سفارش کامل نیست.');

    configureCheckoutBugShipping();

    $ready = $this->actingAs($user)->get(route('storefront.checkout.show'));
    $ready->assertOk()
        ->assertDontSee('محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.')
        ->assertDontSee('اطلاعات ارسال یا سبد خرید برای ثبت سفارش کامل نیست.');

    $placed = $this->actingAs($user)->post(route('storefront.checkout.store'), checkoutBugPayload($address));

    $order = Order::query()->sole();
    $placed->assertRedirect(route('storefront.checkout.success', ['order' => $order->id]));
    expect($order->shipping_total)->toBeGreaterThan(0)
        ->and($order->payment_status->value)->toBe('unpaid');
});

test('checkout without an address remains a safe explicit validation state', function (): void {
    configureCheckoutBugShipping();
    $user = User::factory()->create();
    checkoutBugCart($user, checkoutBugProduct('no-address'));

    $response = $this->actingAs($user)->get(route('storefront.checkout.show'));

    $response->assertOk()
        ->assertSee('برای ثبت سفارش ابتدا یک آدرس در حساب کاربری خود ثبت کنید.')
        ->assertDontSee('اطلاعات ارسال یا سبد خرید برای ثبت سفارش کامل نیست.');
});
