<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ProductImage::storageDisk());
});

function detailRuntimeProduct(string $name, array $attributes = []): Product
{
    $product = Product::query()->create(array_merge([
        'name' => $name,
        'slug' => (string) str($name)->slug(),
        'type' => 'simple',
        'price' => 2500,
        'status' => 'published',
        'published_at' => now(),
        'manage_stock' => true,
        'stock_quantity' => 5,
        'stock_status' => 'in_stock',
    ], $attributes));

    if ($product->type !== 'variable') {
        app(InventoryService::class)->setOnHand($product, (int) ($attributes['stock_quantity'] ?? 5));
    }

    return $product;
}

function detailRuntimeVariable(string $name = 'Detail Variable'): array
{
    $product = detailRuntimeProduct($name, [
        'type' => 'variable',
        'price' => 0,
        'stock_quantity' => 0,
        'stock_status' => 'out_of_stock',
    ]);
    $color = Attribute::query()->create([
        'name' => 'Color '.$name,
        'slug' => 'color-'.str($name)->slug(),
        'type' => 'select',
        'is_variation' => true,
        'is_visible' => true,
        'sort_order' => 1,
    ]);
    $size = Attribute::query()->create([
        'name' => 'Size '.$name,
        'slug' => 'size-'.str($name)->slug(),
        'type' => 'select',
        'is_variation' => true,
        'is_visible' => true,
        'sort_order' => 2,
    ]);
    $red = AttributeValue::query()->create(['attribute_id' => $color->id, 'value' => 'Red', 'slug' => 'red-'.str($name)->slug(), 'sort_order' => 1]);
    $blue = AttributeValue::query()->create(['attribute_id' => $color->id, 'value' => 'Blue', 'slug' => 'blue-'.str($name)->slug(), 'sort_order' => 2]);
    $small = AttributeValue::query()->create(['attribute_id' => $size->id, 'value' => 'Small', 'slug' => 'small-'.str($name)->slug(), 'sort_order' => 1]);
    $large = AttributeValue::query()->create(['attribute_id' => $size->id, 'value' => 'Large', 'slug' => 'large-'.str($name)->slug(), 'sort_order' => 2]);
    $product->attributes()->attach([$color->id => ['sort_order' => 1], $size->id => ['sort_order' => 2]]);
    $product->attributeValues()->attach([$red->id, $blue->id, $small->id, $large->id]);

    $service = app(ProductVariantService::class);
    $first = $service->create($product, ['price' => 1800, 'stock_quantity' => 3, 'sku' => 'DETAIL-RED-S'], [$red->id, $small->id]);
    $second = $service->create($product, ['price' => 2200, 'stock_quantity' => 0, 'sku' => 'DETAIL-BLUE-L'], [$blue->id, $large->id]);

    return compact('product', 'color', 'size', 'red', 'blue', 'small', 'large', 'first', 'second');
}

test('published simple product detail renders public presentation and authoritative sale price', function (): void {
    $category = Category::query()->create(['name' => 'عطر', 'slug' => 'perfume', 'is_active' => true]);
    $brand = Brand::query()->create(['name' => 'برند', 'slug' => 'brand', 'is_active' => true]);
    $tag = Tag::query()->create(['name' => 'جدید', 'slug' => 'new']);
    $product = detailRuntimeProduct('Rose Detail', [
        'price' => 2500,
        'sale_price' => 1900,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDay(),
        'short_description' => 'توضیح کوتاه',
        'description' => 'توضیحات کامل محصول',
        'brand_id' => $brand->id,
    ]);
    $product->categories()->attach($category);
    $product->tags()->attach($tag);
    ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/rose-main.png', 'alt' => 'تصویر رز', 'is_primary' => true, 'sort_order' => 1]);
    ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/rose-second.png', 'alt' => 'تصویر دوم', 'is_primary' => false, 'sort_order' => 2]);
    Storage::disk(ProductImage::storageDisk())->put('products/rose-main.png', 'image');
    Storage::disk(ProductImage::storageDisk())->put('products/rose-second.png', 'image');

    $response = $this->get(route('storefront.products.show', $product));

    $response->assertOk()
        ->assertSee('Rose Detail')
        ->assertSee('توضیحات کامل محصول')
        ->assertSee('1,900 ریال')
        ->assertSee('2,500 ریال')
        ->assertSee('/storage/products/rose-main.png')
        ->assertSee('/storage/products/rose-second.png')
        ->assertSee('برند')
        ->assertSee('عطر')
        ->assertDontSee('inventory_transactions')
        ->assertDontSee('صفحه جزئیات محصول در مرحله بعدی');
});

test('detail renders placeholder and out of stock state when no image or stock exists', function (): void {
    $product = detailRuntimeProduct('Empty Detail', ['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);

    $this->get(route('storefront.products.show', $product))
        ->assertOk()
        ->assertSee('media-placeholder')
        ->assertSee('ناموجود');
});

test('inactive and soft deleted products are not publicly addressable', function (): void {
    $inactive = detailRuntimeProduct('Inactive Detail', ['status' => 'draft']);
    $deleted = detailRuntimeProduct('Deleted Detail');
    $deleted->delete();

    $this->get(route('storefront.products.show', $inactive))->assertNotFound();
    $this->get('/products/'.$deleted->slug)->assertNotFound();
});

test('variable detail renders only product axes and initial authoritative price range', function (): void {
    $fixture = detailRuntimeVariable();
    $unrelated = Attribute::query()->create(['name' => 'Material', 'slug' => 'material', 'type' => 'select', 'is_variation' => true, 'is_visible' => true]);
    AttributeValue::query()->create(['attribute_id' => $unrelated->id, 'value' => 'Gold', 'slug' => 'gold']);

    $response = $this->get(route('storefront.products.show', $fixture['product']));

    $response->assertOk()
        ->assertSee('Color Detail Variable')
        ->assertSee('Red')
        ->assertSee('Blue')
        ->assertSee('Small')
        ->assertSee('Large')
        ->assertSee('1,800 تا 2,200 ریال')
        ->assertSee('data-attribute-id="'.$fixture['color']->id.'"', false)
        ->assertSee('data-value-id="'.$fixture['red']->id.'"', false)
        ->assertSee('/api/v1/products/'.$fixture['product']->id.'/resolve-variation', false)
        ->assertDontSee('Gold');
});

test('variable detail exposes selected variation state hooks without cart integration', function (): void {
    $fixture = detailRuntimeVariable('Hooks Variable');

    $this->get(route('storefront.products.show', $fixture['product']))
        ->assertOk()
        ->assertSee('name="variation_id"', false)
        ->assertSee('data-selected-variation', false)
        ->assertSee('name="quantity"', false)
        ->assertSee('data-add-cart', false)
        ->assertSee('disabled', false)
        ->assertSee('detail-selection.js', false);
});
