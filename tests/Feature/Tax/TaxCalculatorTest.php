<?php

use App\Enums\TaxType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Tax\TaxCalculator;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

function taxClass(TaxType $type, string $value, bool $active = true): TaxClass
{
    return TaxClass::query()->create([
        'name' => "Tax {$type->value} {$value}",
        'slug' => "tax-{$type->value}-".fake()->unique()->numerify('###'),
        'type' => $type,
        'value' => $value,
        'is_active' => $active,
    ]);
}

function taxableProduct(?TaxClass $taxClass = null, string $type = 'simple'): Product
{
    return Product::query()->create([
        'name' => 'Tax test product',
        'slug' => 'tax-test-product-'.fake()->unique()->numerify('###'),
        'type' => $type,
        'tax_class_id' => $taxClass?->id,
    ]);
}

test('it calculates percentage tax using integer Rial arithmetic', function (): void {
    $taxClass = taxClass(TaxType::Percent, '9.000');

    expect(app(TaxCalculator::class)->calculateTax(12_345, $taxClass))->toBe(1_111)
        ->and(app(TaxCalculator::class)->calculateGross(12_345, $taxClass))->toBe(13_456);
});

test('it rounds percentage tax half up to the nearest Rial', function (): void {
    $taxClass = taxClass(TaxType::Percent, '10.000');

    expect(app(TaxCalculator::class)->calculateTax(5, $taxClass))->toBe(1)
        ->and(app(TaxCalculator::class)->calculateTax(4, $taxClass))->toBe(0);
});

test('it applies fixed tax per unit', function (): void {
    $taxClass = taxClass(TaxType::Fixed, '1250.000');

    expect(app(TaxCalculator::class)->calculateTax(50_000, $taxClass, quantity: 3))->toBe(3_750);
});

test('it returns zero for zero and inactive tax classes or no tax class', function (): void {
    $zeroClass = taxClass(TaxType::Percent, '0.000');
    $inactiveClass = taxClass(TaxType::Percent, '9.000', active: false);
    $calculator = app(TaxCalculator::class);

    expect($calculator->calculateTax(10_000, $zeroClass))->toBe(0)
        ->and($calculator->calculateTax(10_000, $inactiveClass))->toBe(0)
        ->and($calculator->calculateTax(10_000, null))->toBe(0);
});

test('it rejects invalid negative, fractional fixed, and over-one-hundred-percent values', function (): void {
    $calculator = app(TaxCalculator::class);

    expect(fn () => $calculator->calculateTax(10_000, new TaxClass([
        'type' => TaxType::Percent,
        'value' => '-1.000',
        'is_active' => true,
    ])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->calculateTax(10_000, new TaxClass([
            'type' => TaxType::Fixed,
            'value' => '10.500',
            'is_active' => true,
        ])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calculator->calculateTax(10_000, new TaxClass([
            'type' => TaxType::Percent,
            'value' => '100.001',
            'is_active' => true,
        ])))->toThrow(InvalidArgumentException::class);
});

test('a product delegates tax calculation to its configured default tax class', function (): void {
    $taxClass = taxClass(TaxType::Percent, '9.000');
    Setting::query()->create([
        'group' => 'tax',
        'key' => 'default_tax_class_id',
        'type' => 'integer',
        'value' => $taxClass->id,
    ]);

    expect(taxableProduct()->taxAmountForPrice(10_000))->toBe(900);
});

test('a variation effective price and its product tax class remain separate and integer safe', function (): void {
    $taxClass = taxClass(TaxType::Percent, '9.000');
    $product = taxableProduct($taxClass, 'variable');
    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'combination_signature' => '1:1',
        'price' => 10_000,
        'sale_price' => 8_000,
        'is_active' => true,
    ]);

    $effectivePrice = app(ProductPriceResolver::class)->effectivePriceForVariation($variation);

    expect($effectivePrice)->toBe(8_000)
        ->and($product->taxAmountForPrice($effectivePrice))->toBe(720);
});

test('the calculator reflects current tax class configuration and does not depend on tax rates', function (): void {
    $taxClass = taxClass(TaxType::Percent, '9.000');
    $calculator = app(TaxCalculator::class);

    expect(Schema::hasTable('tax_rates'))->toBeFalse()
        ->and($calculator->calculateTax(10_000, $taxClass))->toBe(900);

    $taxClass->update(['value' => '10.000']);

    expect($calculator->calculateTax(10_000, $taxClass->fresh()))->toBe(1_000);
});

test('tax class routes remain protected by the existing permission policy', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/tax-classes')
        ->assertForbidden();

    $user->givePermissionTo(Permission::findOrCreate('tax-classes.view', 'web'));

    $this->actingAs($user->fresh())
        ->get('/admin/tax-classes')
        ->assertOk();
});
