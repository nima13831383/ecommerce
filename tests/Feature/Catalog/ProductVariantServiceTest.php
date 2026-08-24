<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Catalog\ProductVariantService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function variableProductFixture(): array
{
    $product = Product::create([
        'type' => 'variable',
        'name' => 'تی‌شرت آزمایشی',
        'slug' => 'test-shirt-'.str()->uuid(),
        'manage_stock' => false,
    ]);

    $color = Attribute::create([
        'name' => 'رنگ',
        'slug' => 'color-'.str()->uuid(),
        'is_variation' => true,
    ]);
    $size = Attribute::create([
        'name' => 'سایز',
        'slug' => 'size-'.str()->uuid(),
        'is_variation' => true,
    ]);
    $material = Attribute::create([
        'name' => 'جنس',
        'slug' => 'material-'.str()->uuid(),
        'is_variation' => true,
    ]);

    $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'قرمز', 'slug' => 'red-'.str()->uuid()]);
    $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'آبی', 'slug' => 'blue-'.str()->uuid()]);
    $large = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'بزرگ', 'slug' => 'large-'.str()->uuid()]);
    $small = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'کوچک', 'slug' => 'small-'.str()->uuid()]);
    $cotton = AttributeValue::create(['attribute_id' => $material->id, 'value' => 'پنبه', 'slug' => 'cotton-'.str()->uuid()]);

    $product->attributes()->sync([
        $color->id => ['sort_order' => 0],
        $size->id => ['sort_order' => 1],
    ]);
    $product->attributeValues()->sync([$red->id, $blue->id, $large->id, $small->id]);

    return compact('product', 'red', 'blue', 'large', 'small', 'cotton');
}

function variantAttributes(array $overrides = []): array
{
    return array_replace([
        'sku' => 'VAR-'.str()->upper(str()->random(12)),
        'price' => 500000,
        'sale_price' => null,
        'stock_quantity' => 10,
        'is_active' => true,
        'is_dismissed' => false,
    ], $overrides);
}

it('creates a valid variation and generates a deterministic signature', function () {
    ['product' => $product, 'red' => $red, 'large' => $large] = variableProductFixture();

    $variation = app(ProductVariantService::class)->create(
        $product,
        variantAttributes(),
        [$large->id, $red->id],
    );

    expect($variation->combination_signature)->toBe("{$red->attribute_id}:{$red->id}|{$large->attribute_id}:{$large->id}")
        ->and($variation->attributeValues()->pluck('attribute_values.id')->all())->toEqualCanonicalizing([$red->id, $large->id]);
});

it('treats submitted attribute-value order as the same combination', function () {
    ['product' => $product, 'red' => $red, 'large' => $large] = variableProductFixture();
    $service = app(ProductVariantService::class);

    expect($service->combinationSignature($product, [$red->id, $large->id]))
        ->toBe($service->combinationSignature($product, [$large->id, $red->id]));
});

it('rejects duplicate combinations in the application service and database', function () {
    ['product' => $product, 'red' => $red, 'large' => $large] = variableProductFixture();
    $service = app(ProductVariantService::class);

    $variation = $service->create($product, variantAttributes(), [$red->id, $large->id]);

    expect(fn () => $service->create($product, variantAttributes(), [$large->id, $red->id]))
        ->toThrow(DomainException::class);

    expect(fn () => ProductVariation::create([
        ...variantAttributes(),
        'product_id' => $product->id,
        'combination_signature' => $variation->combination_signature,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows distinct combinations', function () {
    ['product' => $product, 'red' => $red, 'large' => $large, 'small' => $small] = variableProductFixture();
    $service = app(ProductVariantService::class);

    $service->create($product, variantAttributes(), [$red->id, $large->id]);
    $service->create($product, variantAttributes(), [$red->id, $small->id]);

    expect($product->variations()->count())->toBe(2);
});

it('rejects values that are not configured for the product', function () {
    ['product' => $product, 'red' => $red, 'large' => $large, 'cotton' => $cotton] = variableProductFixture();

    expect(fn () => app(ProductVariantService::class)->create($product, variantAttributes(), [$red->id, $cotton->id]))
        ->toThrow(DomainException::class);
});

it('rejects multiple values from one attribute axis', function () {
    ['product' => $product, 'red' => $red, 'blue' => $blue, 'large' => $large] = variableProductFixture();

    expect(fn () => app(ProductVariantService::class)->create($product, variantAttributes(), [$red->id, $blue->id, $large->id]))
        ->toThrow(DomainException::class);
});

it('rejects duplicate variation SKUs without leaving pivot rows behind', function () {
    ['product' => $product, 'red' => $red, 'blue' => $blue, 'large' => $large, 'small' => $small] = variableProductFixture();
    $service = app(ProductVariantService::class);

    $service->create($product, variantAttributes(['sku' => 'UNIQUE-SKU']), [$red->id, $large->id]);

    expect(fn () => $service->create($product, variantAttributes(['sku' => 'UNIQUE-SKU']), [$blue->id, $small->id]))
        ->toThrow(DomainException::class);

    expect($product->variations()->count())->toBe(1)
        ->and(DB::table('attribute_value_product_variation')->count())->toBe(2);
});

it('resolves product and variation prices as integer rial amounts', function () {
    ['product' => $product, 'red' => $red, 'large' => $large] = variableProductFixture();

    $variation = app(ProductVariantService::class)->create(
        $product,
        variantAttributes(['price' => 500000, 'sale_price' => 450000]),
        [$red->id, $large->id],
    );

    $prices = app(ProductPriceResolver::class)->pricesForProduct($product);

    expect($variation->effective_price)->toBeInt()->toBe(450000)
        ->and($prices['minimum_price'])->toBeInt()->toBe(450000)
        ->and($prices['maximum_price'])->toBeInt()->toBe(450000);
});
