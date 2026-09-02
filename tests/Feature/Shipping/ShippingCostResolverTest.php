<?php

use App\Exceptions\ShippingConfigurationException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Settings\SettingsService;
use App\Services\Shipping\ShippingCostResolver;

function shippingPolicyProduct(array $attributes = []): Product
{
    return Product::query()->create(array_merge([
        'name' => 'Shipping product '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => 'simple',
        'status' => 'published',
        'price' => 100,
        'weight' => 1,
        'volume' => 10,
        'parcel_type' => 'normal',
    ], $attributes));
}

function shippingPolicyCart(array $items): Cart
{
    $cart = Cart::query()->create(['token' => fake()->unique()->uuid(), 'currency' => 'IRR', 'status' => 'active']);
    foreach ($items as [$product, $quantity, $variation]) {
        $cart->items()->create([
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'quantity' => $quantity,
            'unit_price' => 100,
            'line_total' => 100 * $quantity,
            'line_key' => "{$product->id}:".($variation?->id ?? 'simple'),
        ]);
    }

    return $cart->fresh(['items.product', 'items.variation']);
}

it('aggregates quantity, variation fallback, upward weight and fragile state', function (): void {
    $product = shippingPolicyProduct(['type' => 'variable', 'weight' => 1.25, 'volume' => 10]);
    $variation = ProductVariation::query()->create(['product_id' => $product->id, 'price' => 100, 'weight' => 0.75, 'volume' => 5, 'sku' => 'SHIP-1']);
    $cart = shippingPolicyCart([[$product, 2, $variation]]);

    expect(app(ShippingCostResolver::class)->metrics($cart))->toMatchArray([
        'weight_grams' => 1500,
        'volume' => 10.0,
        'parcel_type' => 'normal',
    ]);
});

it('treats one fragile product as a fragile shipment', function (): void {
    $normal = shippingPolicyProduct();
    $fragile = shippingPolicyProduct(['parcel_type' => 'fragile']);
    $cart = shippingPolicyCart([[$normal, 1, null], [$fragile, 1, null]]);

    expect(app(ShippingCostResolver::class)->metrics($cart)['parcel_type'])->toBe('fragile_liquid');
});

it('uses fixed and free modes without requiring calculator package data', function (): void {
    $product = shippingPolicyProduct(['weight' => null, 'volume' => null]);
    $cart = shippingPolicyCart([[$product, 1, null]]);
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'fixed');
    $settings->update('shipping.fixed_rate_amount', 250_000);

    expect(app(ShippingCostResolver::class)->quote($cart, 1, 1, 'pishtaz', 'online')->total)->toBe(250_000);

    $settings->update('shipping.mode', 'free');
    expect(app(ShippingCostResolver::class)->quote($cart, 1, 1, 'pishtaz', 'online')->total)->toBe(0);
});

it('uses the configured global origin and selects the smallest fitting package', function (): void {
    $product = shippingPolicyProduct(['weight' => 1.1, 'volume' => 20]);
    $cart = shippingPolicyCart([[$product, 1, null]]);
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'calculator');
    $settings->update('shipping.origin_province_id', 2);
    $settings->update('shipping.origin_city_id', 4391);
    $settings->update('shipping.packages', [
        ['id' => 'large', 'name' => 'Large', 'capacity_volume' => 100, 'max_weight' => 5000, 'code' => 6, 'active' => true],
        ['id' => 'small', 'name' => 'Small', 'capacity_volume' => 20, 'max_weight' => 2000, 'code' => 1, 'active' => true],
    ]);

    $quote = app(ShippingCostResolver::class)->quote($cart, 27, 6971, 'pishtaz', 'online');

    expect($quote->metadata['origin_province_id'])->toBe(2)
        ->and($quote->metadata['package']['id'])->toBe('small')
        ->and($quote->metadata['weight_grams'])->toBe(1100);
});

it('fails calculator quotes when configured origin or packages are missing', function (): void {
    $product = shippingPolicyProduct();
    $cart = shippingPolicyCart([[$product, 1, null]]);
    app(SettingsService::class)->update('shipping.mode', 'calculator');

    expect(fn () => app(ShippingCostResolver::class)->quote($cart, 27, 6971, 'pishtaz', 'online'))
        ->toThrow(ShippingConfigurationException::class);

    app(SettingsService::class)->update('shipping.origin_province_id', 2);
    app(SettingsService::class)->update('shipping.origin_city_id', 4391);

    expect(fn () => app(ShippingCostResolver::class)->quote($cart, 27, 6971, 'pishtaz', 'online'))
        ->toThrow(ShippingConfigurationException::class);
});
