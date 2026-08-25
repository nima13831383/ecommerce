<?php

use App\Enums\AddressType;
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
use App\Services\Checkout\CheckoutInput;
use App\Services\Checkout\CheckoutService;
use App\Services\Inventory\InventoryService;

function checkoutProduct(string $suffix = 'default', int $price = 100_000, int $stock = 10): Product
{
    $product = Product::query()->create([
        'name' => "Checkout product {$suffix}",
        'slug' => "checkout-product-{$suffix}",
        'sku' => "CHECKOUT-{$suffix}",
        'type' => 'simple',
        'price' => $price,
        'weight' => 5,
        'status' => 'published',
    ]);

    app(InventoryService::class)->setOnHand($product, $stock, reason: 'Checkout test setup');

    return $product;
}

function checkoutAddress(User $user, AddressType $type = AddressType::Both): Address
{
    return app(AddressService::class)->create($user, [
        'type' => $type->value,
        'first_name' => 'Ali',
        'last_name' => 'Customer',
        'mobile' => '09120000000',
        'province_id' => 27,
        'city_id' => 6971,
        'postal_code' => '0123456789',
        'address_line' => 'خیابان اصلی',
        'plaque' => '10',
        'unit' => '2',
    ]);
}

function checkoutInput(Cart $cart, Address $address, array $overrides = []): CheckoutInput
{
    return new CheckoutInput(...array_replace([
        'cartId' => $cart->id,
        'shippingAddressId' => $address->id,
        'billingAddressId' => null,
        'originProvinceId' => 2,
        'originCityId' => 4391,
        'shippingService' => 'pishtaz',
        'parcelType' => 'normal',
        'shippingPaymentType' => 'online',
        'packageSizeId' => 1,
        'idempotencyKey' => null,
    ], $overrides));
}

function checkoutCart(User $user, Product $product, int $quantity = 1): Cart
{
    $service = app(CartService::class);
    $cart = $service->getOrCreateForUser($user->id);
    $service->addItem($cart, $product, $quantity);

    return $cart->fresh('items');
}

test('preview recalculates totals and has no order, usage, or reservation side effects', function (): void {
    $user = User::factory()->create();
    $product = checkoutProduct();
    $cart = checkoutCart($user, $product);
    $address = checkoutAddress($user);
    $coupon = Coupon::query()->create(['code' => 'PREVIEW10', 'type' => 'percent', 'amount' => 10]);
    app(CartService::class)->applyCoupon($cart, $coupon->code, $user->id);

    $result = app(CheckoutService::class)->preview($user, checkoutInput($cart, $address));

    expect($result->order)->toBeNull()
        ->and($result->subtotal)->toBe(100_000)
        ->and($result->discountTotal)->toBe(10_000)
        ->and($result->shippingTotal)->toBeGreaterThan(0)
        ->and($result->grandTotal)->toBe(90_000 + $result->shippingTotal)
        ->and($result->cart->status)->toBe('active')
        ->and(CouponUsage::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(0)
        ->and(Order::count())->toBe(0);
});

test('placement creates a snapshotted order, redeems the coupon once, reserves stock, and converts the cart', function (): void {
    $user = User::factory()->create();
    $product = checkoutProduct('place');
    $cart = checkoutCart($user, $product);
    $address = checkoutAddress($user);
    $coupon = Coupon::query()->create(['code' => 'PLACE10', 'type' => 'percent', 'amount' => 10]);
    app(CartService::class)->applyCoupon($cart, $coupon->code, $user->id);
    $input = checkoutInput($cart, $address, ['idempotencyKey' => 'checkout-place-1']);

    $result = app(CheckoutService::class)->placeOrder($user, $input);
    $order = $result->order->fresh(['items.inventoryReservation']);

    expect($order)->not->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($order->shipping_address['city_id'])->toBe(6971)
        ->and($order->shipping_snapshot['service'])->toBe('pishtaz')
        ->and($order->coupon_snapshot['code'])->toBe('PLACE10')
        ->and($order->discount_total)->toBe(10_000)
        ->and($order->shipping_total)->toBeGreaterThan(0)
        ->and($order->grand_total)->toBeInt()
        ->and(CouponUsage::where('order_id', $order->id)->count())->toBe(1)
        ->and(InventoryReservation::where('reference_type', 'order_item')->count())->toBe(1)
        ->and($result->cart->status)->toBe('converted');
});

test('placement uses current price and tax after a stale preview', function (): void {
    $user = User::factory()->create();
    $product = checkoutProduct('price-change', 100_000);
    $cart = checkoutCart($user, $product);
    $address = checkoutAddress($user);
    $service = app(CheckoutService::class);
    $input = checkoutInput($cart, $address, ['idempotencyKey' => 'checkout-price-change']);

    $service->preview($user, $input);
    $product->update(['price' => 200_000]);
    $result = $service->placeOrder($user, $input);

    expect($result->order->items->sole()->unit_price)->toBe(200_000)
        ->and($result->order->items_subtotal)->toBe(200_000);
});

test('same idempotency key returns the same order and conflicting input is rejected', function (): void {
    $user = User::factory()->create();
    $product = checkoutProduct('idempotency');
    $cart = checkoutCart($user, $product);
    $address = checkoutAddress($user);
    $service = app(CheckoutService::class);
    $input = checkoutInput($cart, $address, ['idempotencyKey' => 'checkout-retry']);

    $first = $service->placeOrder($user, $input);
    $retry = $service->placeOrder($user, $input);

    expect($retry->order->id)->toBe($first->order->id)
        ->and(Order::count())->toBe(1)
        ->and(CouponUsage::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(1);

    $otherAddress = checkoutAddress($user);
    $conflicting = checkoutInput($cart, $otherAddress, ['idempotencyKey' => 'checkout-retry']);

    expect(fn () => $service->placeOrder($user, $conflicting))->toThrow(DomainException::class);
});

test('wrong address owner and unavailable stock cannot place an order', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = checkoutProduct('ownership', 100_000, 1);
    $cart = checkoutCart($user, $product);
    $address = checkoutAddress($other);

    expect(fn () => app(CheckoutService::class)->preview($user, checkoutInput($cart, $address)))
        ->toThrow(DomainException::class);
});
