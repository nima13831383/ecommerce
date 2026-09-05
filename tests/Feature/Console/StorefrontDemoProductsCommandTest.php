<?php

use App\Enums\InventoryOperation;
use App\Models\Category;
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

    expect(Product::query()->whereIn('sku', demoProductSkus())->count())->toBe(16)
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

    expect(InventoryTransaction::query()->where('operation', InventoryOperation::OpeningStock)->count())->toBe(35);

    expect(Product::query()->whereIn('sku', additionalDemoProductSkus())->where('status', 'published')->count())->toBe(10)
        ->and(Product::query()->whereIn('sku', additionalDemoProductSkus())->where('type', 'variable')->count())->toBe(5)
        ->and(Product::query()->whereIn('sku', additionalDemoProductSkus())->where('type', 'simple')->count())->toBe(5)
        ->and(Product::query()->where('sku', 'DEMO-SUNSCREEN-001')->value('stock_quantity'))->toBe(0);
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
    $response->assertSee('سرم مو ابریشمین')->assertSee('کرم آبرسان ابریشم')->assertDontSee('محصول مخفی تستی');

    $this->get('/products')->assertOk()->assertSee('سرم مو ابریشمین')->assertDontSee('محصول مخفی تستی');
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

test('rerunning the demo command preserves inventory changed after bootstrap', function (): void {
    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    $inventory = app(InventoryService::class);
    $simple = Product::query()->where('sku', 'DEMO-MASCARA-001')->firstOrFail();
    $variation = Product::query()->where('sku', 'DEMO-PERFUME-002')->firstOrFail()
        ->variations()->where('sku', 'DEMO-PERFUME-002-30-STANDARD')->firstOrFail();

    $inventory->adjust($simple, -1, InventoryOperation::ManualAdjustment, reason: 'demo inventory preservation test');
    $inventory->adjust($variation, -1, InventoryOperation::ManualAdjustment, reason: 'demo variation preservation test');
    $simpleStock = $simple->fresh()->stock_quantity;
    $variationStock = $variation->fresh()->stock_quantity;
    $transactionCount = InventoryTransaction::query()->count();

    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    expect($simple->fresh()->stock_quantity)->toBe($simpleStock)
        ->and($variation->fresh()->stock_quantity)->toBe($variationStock)
        ->and(InventoryTransaction::query()->count())->toBe($transactionCount)
        ->and(Product::query()->whereIn('sku', demoProductSkus())->count())->toBe(16);
});

test('expanded demo products cover combinations, sales, stock states, shipping data, and media', function (): void {
    $this->artisan('demo:storefront-products')->assertExitCode(Command::SUCCESS);

    $expectedVariations = [
        'DEMO-PERFUME-002' => 6,
        'DEMO-BRACELET-002' => 4,
        'DEMO-LIPSTICK-002' => 4,
        'DEMO-MAKEUP-BAG-001' => 3,
        'DEMO-BODY-MIST-001' => 3,
    ];

    foreach ($expectedVariations as $sku => $count) {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        expect($product->variations()->count())->toBe($count)
            ->and($product->variations()->pluck('combination_signature')->unique())->toHaveCount($count)
            ->and($product->weight)->toBeGreaterThan(0)
            ->and($product->volume)->toBeGreaterThan(0)
            ->and(ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->count())->toBe(1);
    }

    foreach (['DEMO-NECKLACE-001', 'DEMO-MASCARA-001', 'DEMO-CREAM-001', 'DEMO-SUNSCREEN-001', 'DEMO-HAIR-SERUM-001'] as $sku) {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        expect($product->weight)->toBeGreaterThan(0)
            ->and($product->volume)->toBeGreaterThan(0)
            ->and(ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->count())->toBe(1);
    }

    expect(Product::query()->whereIn('sku', ['DEMO-NECKLACE-001', 'DEMO-CREAM-001', 'DEMO-SUNSCREEN-001', 'DEMO-HAIR-SERUM-001'])->get()->every(fn (Product $product): bool => $product->sale_price < $product->price))->toBeTrue()
        ->and(Product::query()->where('sku', 'DEMO-SUNSCREEN-001')->value('stock_quantity'))->toBe(0)
        ->and(Product::query()->where('sku', 'DEMO-MASCARA-001')->value('stock_quantity'))->toBe(2)
        ->and(Product::query()->where('sku', 'DEMO-PERFUME-002')->firstOrFail()->variations()->where('stock_quantity', 0)->count())->toBe(1)
        ->and(Category::query()->whereIn('slug', ['jewelry', 'haircare'])->count())->toBe(2)
        ->and(Category::query()->whereIn('slug', ['jewelry', 'haircare'])->select('slug')->distinct()->count())->toBe(2);

    foreach (Product::query()->whereIn('sku', additionalDemoProductSkus())->get() as $product) {
        expect(ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->exists())->toBeTrue()
            ->and(Storage::disk(ProductImage::storageDisk())->exists($product->images()->where('is_primary', true)->value('path')))->toBeTrue();
    }

    $this->get('/products?search=گردنبند')->assertOk()->assertSee('گردنبند استیل آفتاب');
    $this->get('/products?search=ریمل')->assertOk()->assertSee('ریمل حجم‌دهنده سایه');
    $this->get('/products?search=مو')->assertOk()->assertSee('سرم مو ابریشمین');
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
        ...additionalDemoProductSkus(),
    ];
}

function additionalDemoProductSkus(): array
{
    return [
        'DEMO-PERFUME-002',
        'DEMO-BRACELET-002',
        'DEMO-NECKLACE-001',
        'DEMO-LIPSTICK-002',
        'DEMO-MASCARA-001',
        'DEMO-CREAM-001',
        'DEMO-SUNSCREEN-001',
        'DEMO-MAKEUP-BAG-001',
        'DEMO-HAIR-SERUM-001',
        'DEMO-BODY-MIST-001',
    ];
}
