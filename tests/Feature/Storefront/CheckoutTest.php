<?php

use App\Enums\AddressType;
use App\Enums\InventoryReservationStatus;
use App\Models\Address;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Cart\CartService;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;

beforeEach(function (): void {
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'calculator');
    $settings->update('shipping.origin_province_id', 2);
    $settings->update('shipping.origin_city_id', 4391);
    $settings->update('shipping.packages', [
        ['id' => 'default', 'name' => 'Default', 'capacity_volume' => 1000, 'max_weight' => 30_000, 'code' => 1, 'active' => true],
    ]);
});

function storefrontCheckoutProduct(string $suffix = 'default', int $price = 100_000, int $stock = 10): Product
{
    $product = Product::query()->create([
        'name' => "Storefront checkout {$suffix}",
        'slug' => "storefront-checkout-{$suffix}",
        'sku' => "STOREFRONT-CHECKOUT-{$suffix}",
        'type' => 'simple',
        'price' => $price,
        'weight' => 5,
        'volume' => 10,
        'status' => 'published',
    ]);

    app(InventoryService::class)->setOnHand($product, $stock, reason: 'Storefront checkout test setup');

    return $product;
}

function storefrontCheckoutAddress(User $user): Address
{
    return app(AddressService::class)->create($user, [
        'type' => AddressType::Both->value,
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

function storefrontCheckoutCart(User $user, Product $product, int $quantity = 1): Cart
{
    $service = app(CartService::class);
    $cart = $service->getOrCreateForUser($user->id);
    $service->addItem($cart, $product, $quantity);

    return $cart->fresh('items');
}

function storefrontCheckoutPayload(Address $address, string $key = 'storefront-checkout-key'): array
{
    return [
        'shipping_address_id' => $address->id,
        'shipping_service' => 'pishtaz',
        'shipping_payment_type' => 'online',
        'idempotency_key' => $key,
    ];
}

test('guest checkout is redirected and authenticated checkout renders authoritative summary', function (): void {
    $this->get(route('storefront.checkout.show'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $product = storefrontCheckoutProduct('summary', 125_000);
    storefrontCheckoutCart($user, $product);
    $address = storefrontCheckoutAddress($user);

    $response = $this->actingAs($user)->get(route('storefront.checkout.show'));

    $response->assertOk()
        ->assertSee('تسویه حساب')
        ->assertSee($product->name)
        ->assertSee('۱۲۵,۰۰۰ ریال')
        ->assertSee('name="idempotency_key"', false)
        ->assertSee((string) $address->id, false);
    expect(Order::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(0)
        ->and(CouponUsage::count())->toBe(0);
});

test('checkout placement creates an order, reserves inventory, and converts the current cart', function (): void {
    $user = User::factory()->create();
    $product = storefrontCheckoutProduct('place', 200_000, 3);
    $cart = storefrontCheckoutCart($user, $product);
    $address = storefrontCheckoutAddress($user);

    $response = $this->actingAs($user)->post(route('storefront.checkout.store'), storefrontCheckoutPayload($address));
    $order = Order::query()->with('items.inventoryReservation')->sole();

    $response->assertRedirect(route('storefront.checkout.success', ['order' => $order->id]));
    expect($order->items)->toHaveCount(1)
        ->and($order->items->sole()->unit_price)->toBe(200_000)
        ->and($order->status->value)->toBe('pending')
        ->and($order->payment_status->value)->toBe('unpaid')
        ->and($order->grand_total)->toBeInt()
        ->and($order->items->sole()->inventoryReservation->status)->toBe(InventoryReservationStatus::Active)
        ->and($product->fresh()->stock_quantity)->toBe(3)
        ->and($cart->fresh()->status)->toBe('converted')
        ->and(InventoryReservation::count())->toBe(1)
        ->and(Payment::count())->toBe(0)
        ->and(Shipment::count())->toBe(0);

    $response = $this->actingAs($user)->get(route('storefront.checkout.success', ['order' => $order->id]));
    $response->assertOk()->assertSee($order->order_number);
});

test('variable checkout reserves the variation and snapshots its selected attributes', function (): void {
    $user = User::factory()->create();
    $product = storefrontCheckoutProduct('variable', 0, 0);
    $product->forceFill(['type' => 'variable'])->save();
    $attribute = Attribute::query()->create([
        'name' => 'رنگ سفارش',
        'slug' => 'checkout-color',
        'type' => 'select',
        'is_variation' => true,
        'is_visible' => true,
    ]);
    $red = AttributeValue::query()->create([
        'attribute_id' => $attribute->id,
        'value' => 'قرمز',
        'slug' => 'checkout-red',
    ]);
    $product->attributes()->attach($attribute, ['sort_order' => 1]);
    $product->attributeValues()->attach($red);
    $variation = app(ProductVariantService::class)->create($product, [
        'price' => 175_000,
        'sku' => 'CHECKOUT-VARIANT-RED',
        'stock_quantity' => 2,
    ], [$red->id]);
    $cart = app(CartService::class)->getOrCreateForUser($user->id);
    app(CartService::class)->addItem($cart, $product, 1, $variation);
    $address = storefrontCheckoutAddress($user);

    $this->actingAs($user)->post(route('storefront.checkout.store'), storefrontCheckoutPayload($address, 'variable-key'))
        ->assertRedirect();

    $order = Order::query()->with('items.inventoryReservation')->sole();
    $item = $order->items->sole();
    expect($item->product_variation_id)->toBe($variation->id)
        ->and($item->sku)->toBe('CHECKOUT-VARIANT-RED')
        ->and($item->variation_attributes)->toHaveKey('رنگ سفارش', 'قرمز')
        ->and($item->inventoryReservation->inventory_owner_type)->toBe($variation::class)
        ->and($item->inventoryReservation->inventory_owner_id)->toBe($variation->id);
});

test('checkout is idempotent and rejects a reused key with changed semantic input', function (): void {
    $user = User::factory()->create();
    $product = storefrontCheckoutProduct('idempotent', 110_000);
    storefrontCheckoutCart($user, $product);
    $address = storefrontCheckoutAddress($user);
    $payload = storefrontCheckoutPayload($address, 'same-key');

    $first = $this->actingAs($user)->post(route('storefront.checkout.store'), $payload);
    $order = Order::query()->sole();
    $retry = $this->actingAs($user)->post(route('storefront.checkout.store'), $payload);

    $retry->assertRedirect(route('storefront.checkout.success', ['order' => $order->id]));
    expect(Order::count())->toBe(1)
        ->and(InventoryReservation::count())->toBe(1);

    $other = storefrontCheckoutAddress($user);
    $conflict = $this->actingAs($user)->post(route('storefront.checkout.store'), storefrontCheckoutPayload($other, 'same-key'));
    $conflict->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(1);
});

test('checkout rejects foreign address and empty cart without creating side effects', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = storefrontCheckoutProduct('ownership');
    storefrontCheckoutCart($user, $product);
    $foreignAddress = storefrontCheckoutAddress($other);

    $response = $this->actingAs($user)->from(route('storefront.checkout.show'))
        ->post(route('storefront.checkout.store'), storefrontCheckoutPayload($foreignAddress));

    $response->assertRedirect(route('storefront.checkout.show'))->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(0);

    app(CartService::class)->clear($user->carts()->where('status', 'active')->firstOrFail());
    $empty = $this->actingAs($user)->get(route('storefront.checkout.show'));
    $empty->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0);
});

test('checkout never trusts browser price, weight, package, or cart identity fields', function (): void {
    $user = User::factory()->create();
    $product = storefrontCheckoutProduct('tamper', 90_000, 2);
    $cart = storefrontCheckoutCart($user, $product);
    $address = storefrontCheckoutAddress($user);

    $payload = storefrontCheckoutPayload($address, 'tamper-key') + [
        'cart_id' => 999999,
        'price' => 1,
        'shipping_amount' => 1,
        'weight' => 1,
        'package_id' => 'attacker',
    ];

    $this->actingAs($user)->post(route('storefront.checkout.store'), $payload)
        ->assertRedirect();

    $order = Order::query()->sole();
    expect($order->cart_id)->toBe($cart->id)
        ->and($order->items->first()->unit_price)->toBe(90_000)
        ->and($order->shipping_total)->toBeGreaterThan(0);
});

test('order success page does not disclose another customer order', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $product = storefrontCheckoutProduct('success-owner');
    $address = storefrontCheckoutAddress($owner);
    $cart = storefrontCheckoutCart($owner, $product);

    $this->actingAs($owner)->post(route('storefront.checkout.store'), storefrontCheckoutPayload($address, 'success-owner-key'));
    $order = Order::query()->sole();

    $this->actingAs($other)->get(route('storefront.checkout.success', ['order' => $order->id]))->assertNotFound();
    expect($cart->fresh()->status)->toBe('converted');
});
