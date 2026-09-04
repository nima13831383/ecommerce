<?php

use App\Enums\InventoryOperation;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ProductImage::storageDisk());
});

test('the storefront demo command creates a complete idempotent catalog', function (): void {
    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    expect(Product::query()->whereIn('sku', demoProductSkus())->count())->toBe(6)
        ->and(Product::query()->where('sku', 'DEMO-HIDDEN-001')->value('status'))->toBe('draft')
        ->and(InventoryReservation::query()->count())->toBe(0);

    $perfume = Product::query()->where('sku', 'DEMO-PERFUME-001')->firstOrFail();
    $bracelet = Product::query()->where('sku', 'DEMO-BRACELET-001')->firstOrFail();

    expect($perfume->variations()->count())->toBe(6)
        ->and($perfume->attributes()->count())->toBe(2)
        ->and($perfume->attributeValues()->count())->toBe(5)
        ->and($bracelet->variations()->count())->toBe(9)
        ->and($bracelet->attributes()->count())->toBe(2)
        ->and($bracelet->attributeValues()->count())->toBe(6);

    expect($perfume->variations()->pluck('combination_signature')->unique())->toHaveCount(6)
        ->and($bracelet->variations()->pluck('combination_signature')->unique())->toHaveCount(9);

    expect($perfume->variations()->pluck('stock_quantity', 'sku')->all())
        ->toMatchArray([
            'DEMO-PERFUME-30-STANDARD' => 8,
            'DEMO-PERFUME-30-GIFT' => 3,
            'DEMO-PERFUME-50-STANDARD' => 12,
            'DEMO-PERFUME-50-GIFT' => 0,
            'DEMO-PERFUME-100-STANDARD' => 5,
            'DEMO-PERFUME-100-GIFT' => 2,
        ]);

    $inventory = app(InventoryService::class);
    $zeroStock = $perfume->variations()->where('sku', 'DEMO-PERFUME-50-GIFT')->firstOrFail();
    expect($inventory->availableQuantity($zeroStock))->toBe(0);

    $prices = app(ProductPriceResolver::class);
    expect($prices->pricesForProduct($perfume)['minimum_price'])->toBe(18_500_000)
        ->and($prices->pricesForProduct($perfume)['maximum_price'])->toBe(40_500_000)
        ->and($prices->pricesForProduct($bracelet)['minimum_price'])->toBe(6_900_000)
        ->and($prices->pricesForProduct($bracelet)['maximum_price'])->toBe(8_900_000)
        ->and($prices->pricesForProduct(Product::query()->where('sku', 'DEMO-SERUM-001')->firstOrFail()))->toMatchArray([
            'regular_price' => 12_500_000,
            'sale_price' => 9_900_000,
            'effective_price' => 9_900_000,
            'is_discounted' => true,
        ]);

    expect(ProductImage::query()->where('product_id', $perfume->id)->count())->toBe(3)
        ->and(ProductImage::query()->where('product_id', $perfume->id)->where('is_primary', true)->count())->toBe(1)
        ->and(Storage::disk(ProductImage::storageDisk())->exists('storefront-demo/demo-aurora-velvet-perfume/primary.svg'))->toBeTrue();

    expect(InventoryTransaction::query()->where('operation', InventoryOperation::OpeningStock)->count())->toBe(15);
});

test('the storefront demo command uses the canonical resolver and does not duplicate on rerun', function (): void {
    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    $perfume = Product::query()->where('sku', 'DEMO-PERFUME-001')->firstOrFail();
    $volume = $perfume->attributeValues()->where('slug', '50ml')->firstOrFail();
    $standard = $perfume->attributeValues()->where('slug', 'standard')->firstOrFail();
    $gift = $perfume->attributeValues()->where('slug', 'gift')->firstOrFail();
    $variants = app(ProductVariantService::class);

    $validSignature = $variants->combinationSignature($perfume, [$volume->id, $standard->id]);
    $giftSignature = $variants->combinationSignature($perfume, [$volume->id, $gift->id]);
    expect($perfume->variations()->where('combination_signature', $validSignature)->value('sku'))->toBe('DEMO-PERFUME-50-STANDARD')
        ->and($perfume->variations()->where('combination_signature', $giftSignature)->value('sku'))->toBe('DEMO-PERFUME-50-GIFT');

    $before = [
        'products' => Product::query()->whereIn('sku', demoProductSkus())->count(),
        'variations' => $perfume->variations()->count() + Product::query()->where('sku', 'DEMO-BRACELET-001')->firstOrFail()->variations()->count(),
        'images' => ProductImage::query()->whereIn('product_id', Product::query()->whereIn('sku', demoProductSkus())->select('id'))->count(),
        'inventory' => InventoryTransaction::query()->count(),
    ];

    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    expect([
        'products' => Product::query()->whereIn('sku', demoProductSkus())->count(),
        'variations' => Product::query()->where('sku', 'DEMO-PERFUME-001')->firstOrFail()->variations()->count() + Product::query()->where('sku', 'DEMO-BRACELET-001')->firstOrFail()->variations()->count(),
        'images' => ProductImage::query()->whereIn('product_id', Product::query()->whereIn('sku', demoProductSkus())->select('id'))->count(),
        'inventory' => InventoryTransaction::query()->count(),
    ])->toBe($before);

    $response = $this->get('/')->assertOk();
    $response->assertSee('Aurora Velvet')->assertSee('Luna')->assertDontSee('محصول مخفی تستی');

    $this->get('/products')->assertOk()->assertSee('Aurora Velvet')->assertDontSee('محصول مخفی تستی');
    $this->get('/products/demo-aurora-velvet-perfume')->assertOk()->assertSee('30 میلی‌لیتر')->assertSee('استاندارد');
    $this->get('/products/demo-luna-steel-bracelet')->assertOk()->assertSee('طلایی')->assertSee('Large');

    $this->postJson('/api/v1/products/'.$perfume->id.'/resolve-variation', [
        'options' => [
            ['attribute_id' => $volume->attribute_id, 'value_id' => $volume->id],
            ['attribute_id' => $standard->attribute_id, 'value_id' => $standard->id],
        ],
    ])->assertOk()->assertJsonPath('data.sku', 'DEMO-PERFUME-50-STANDARD')->assertJsonPath('data.availability.in_stock', true);

    $this->postJson('/api/v1/products/'.$perfume->id.'/resolve-variation', [
        'options' => [
            ['attribute_id' => $volume->attribute_id, 'value_id' => $volume->id],
            ['attribute_id' => $gift->attribute_id, 'value_id' => $gift->id],
        ],
    ])->assertOk()->assertJsonPath('data.sku', 'DEMO-PERFUME-50-GIFT')->assertJsonPath('data.availability.in_stock', false);
});

function demoProductSkus(): array
{
    return [
        'DEMO-PERFUME-001',
        'DEMO-BRACELET-001',
        'DEMO-SERUM-001',
        'DEMO-LIPSTICK-001',
        'DEMO-POCKET-PERFUME-001',
        'DEMO-HIDDEN-001',
    ];
}
