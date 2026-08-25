<?php

use App\Models\Coupon;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;

function cartTestProduct(string $suffix, int $price = 100, int $stock = 20, string $type = 'simple'): Product
{
    $product = Product::query()->create([
        'name' => "Cart product {$suffix}",
        'slug' => "cart-product-{$suffix}",
        'sku' => "CART-{$suffix}",
        'type' => $type,
        'price' => $price,
        'status' => 'published',
    ]);

    if ($type === 'simple') {
        app(InventoryService::class)->setOnHand($product, $stock, reason: 'Cart test setup');
    }

    return $product;
}

test('it creates one active cart per user and adds a simple product with tax', function (): void {
    $user = User::factory()->create();
    $tax = TaxClass::query()->create([
        'name' => 'Cart tax',
        'slug' => 'cart-tax',
        'type' => 'percent',
        'value' => 9,
        'is_active' => true,
    ]);
    $product = cartTestProduct('simple', 100);
    $product->update(['tax_class_id' => $tax->id]);

    $service = app(CartService::class);
    $cart = $service->getOrCreateForUser($user->id);
    $sameCart = $service->getOrCreateForUser($user->id);
    $result = $service->addItem($cart, $product, 2);

    expect($sameCart->id)->toBe($cart->id)
        ->and($result->cart->items)->toHaveCount(1)
        ->and($result->cart->subtotal)->toBe(200)
        ->and($result->cart->tax_total)->toBe(18)
        ->and($result->cart->grand_total)->toBe(218)
        ->and($result->cart->items->first()->line_total)->toBe(218);
});

test('duplicate add increments one logical simple item and quantity updates are absolute', function (): void {
    $product = cartTestProduct('duplicate');
    $cart = app(CartService::class)->getOrCreateForToken('cart-token-1');
    $service = app(CartService::class);

    $service->addItem($cart, $product, 2);
    $result = $service->addItem($cart, $product, 3);

    expect($result->cart->items)->toHaveCount(1)
        ->and($result->cart->items->first()->quantity)->toBe(5);

    $result = $service->updateQuantity($cart, $result->cart->items->first(), 4);

    expect($result->cart->subtotal)->toBe(400);
});

test('variable products require the owning active variation', function (): void {
    $product = cartTestProduct('variable', 100, 20, 'variable');
    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'price' => 150,
        'is_active' => true,
    ]);
    app(InventoryService::class)->setOnHand($variation, 20, reason: 'Cart test setup');
    $other = cartTestProduct('other-variable', 100, 20, 'variable');
    $cart = app(CartService::class)->getOrCreateForToken('cart-token-2');
    $service = app(CartService::class);

    expect(fn () => $service->addItem($cart, $product, 1))->toThrow(DomainException::class)
        ->and($service->addItem($cart, $product, 1, $variation)->cart->items->first()->unit_price)->toBe(150)
        ->and(fn () => $service->addItem($cart, $product, 1, ProductVariation::query()->create([
            'product_id' => $other->id,
            'price' => 200,
        ])))->toThrow(DomainException::class);
});

test('availability is checked without creating a reservation', function (): void {
    $product = cartTestProduct('stock', 100, 2);
    $cart = app(CartService::class)->getOrCreateForToken('cart-token-3');
    $service = app(CartService::class);

    expect(fn () => $service->addItem($cart, $product, 3))->toThrow(DomainException::class)
        ->and($service->addItem($cart, $product, 2)->cart->items->first()->quantity)->toBe(2)
        ->and(InventoryReservation::query()->count())->toBe(0);
});

test('recalculation refreshes price and coupon preview without consuming usage', function (): void {
    $product = cartTestProduct('coupon', 100);
    $cart = app(CartService::class)->getOrCreateForToken('cart-token-4');
    $service = app(CartService::class);
    $service->addItem($cart, $product, 1);
    $coupon = Coupon::query()->create(['code' => 'CART10', 'type' => 'percent', 'amount' => 10]);

    $result = $service->applyCoupon($cart, 'cart10');

    expect($result->cart->discount_total)->toBe(10)
        ->and($coupon->fresh()->usage_count)->toBe(0);

    $product->update(['price' => 200]);
    $result = $service->recalculate($cart);

    expect($result->cart->subtotal)->toBe(200)
        ->and($result->cart->discount_total)->toBe(20)
        ->and($result->cart->grand_total)->toBe(180);
});

test('remove and clear recalculate the cart without deleting the cart record', function (): void {
    $product = cartTestProduct('clear');
    $service = app(CartService::class);
    $cart = $service->getOrCreateForToken('cart-token-5');
    $service->addItem($cart, $product, 1);
    $result = $service->removeItem($cart, $cart->fresh()->items->first());

    expect($result->cart->items)->toBeEmpty()
        ->and($result->cart->exists)->toBeTrue();

    $service->addItem($cart, $product, 1);
    $result = $service->clear($cart);

    expect($result->cart->items)->toBeEmpty()
        ->and($result->cart->grand_total)->toBe(0);
});
