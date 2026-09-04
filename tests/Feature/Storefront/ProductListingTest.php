<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ProductImage::storageDisk());
});

function storefrontProduct(string $name, array $attributes = []): Product
{
    $product = Product::query()->create(array_merge([
        'name' => $name,
        'slug' => (string) str($name)->slug(),
        'type' => 'simple',
        'price' => 1000,
        'status' => 'published',
        'published_at' => now(),
        'stock_quantity' => 10,
        'stock_status' => 'in_stock',
    ], $attributes));

    $requestedStock = (int) ($attributes['stock_quantity'] ?? 10);
    if ($product->type !== 'variable' && $requestedStock > 0) {
        app(InventoryService::class)->setOnHand($product, $requestedStock);
    }

    return $product;
}

function storefrontVariableProduct(string $name = 'Variable Storefront Product'): array
{
    $suffix = str($name)->slug();
    $product = storefrontProduct($name, [
        'slug' => $suffix,
        'type' => 'variable',
        'stock_quantity' => 0,
        'stock_status' => 'out_of_stock',
    ]);
    $color = Attribute::query()->create(['name' => "Color {$suffix}", 'slug' => "color-{$suffix}", 'type' => 'select', 'is_variation' => true, 'is_visible' => true, 'sort_order' => 1]);
    $size = Attribute::query()->create(['name' => "Size {$suffix}", 'slug' => "size-{$suffix}", 'type' => 'select', 'is_variation' => true, 'is_visible' => true, 'sort_order' => 2]);
    $red = AttributeValue::query()->create(['attribute_id' => $color->id, 'value' => 'Red', 'slug' => "red-{$suffix}", 'sort_order' => 1]);
    $blue = AttributeValue::query()->create(['attribute_id' => $color->id, 'value' => 'Blue', 'slug' => "blue-{$suffix}", 'sort_order' => 2]);
    $small = AttributeValue::query()->create(['attribute_id' => $size->id, 'value' => 'Small', 'slug' => "small-{$suffix}", 'sort_order' => 1]);
    $large = AttributeValue::query()->create(['attribute_id' => $size->id, 'value' => 'Large', 'slug' => "large-{$suffix}", 'sort_order' => 2]);

    $product->attributes()->attach([$color->id => ['sort_order' => 1], $size->id => ['sort_order' => 2]]);
    $product->attributeValues()->attach([$red->id, $blue->id, $small->id, $large->id]);
    $service = app(ProductVariantService::class);
    $service->create($product, ['price' => 1200, 'stock_quantity' => 4, 'sku' => "{$suffix}-red-small"], [$red->id, $small->id]);
    $service->create($product, ['price' => 1400, 'stock_quantity' => 0, 'sku' => "{$suffix}-blue-large"], [$blue->id, $large->id]);

    return compact('product', 'color', 'size', 'red', 'blue', 'small', 'large');
}

test('the home page renders current featured products and excludes non-public products', function (): void {
    $featured = storefrontProduct('Featured Serum', ['is_featured' => true, 'price' => 2500, 'sale_price' => 1900, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);
    ProductImage::query()->create(['product_id' => $featured->id, 'path' => 'products/featured.png', 'alt' => 'Featured serum', 'is_primary' => true]);
    Storage::disk(ProductImage::storageDisk())->put('products/featured.png', 'image');
    storefrontProduct('Hidden Featured', ['is_featured' => true, 'status' => 'draft']);
    $deleted = storefrontProduct('Deleted Featured', ['is_featured' => true]);
    $deleted->delete();

    $this->get('/')->assertOk()
        ->assertSee('Featured Serum')
        ->assertSee('1,900 ریال')
        ->assertSee('/storage/products/featured.png')
        ->assertDontSee('Hidden Featured')
        ->assertDontSee('Deleted Featured');
});

test('listing renders cards, filters, sorting, pagination, and a safe empty state', function (): void {
    $category = Category::query()->create(['name' => 'پوست', 'slug' => 'skin', 'is_active' => true]);
    $brand = Brand::query()->create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
    $matching = storefrontProduct('Acme Skin Serum', ['brand_id' => $brand->id, 'price' => 3000]);
    $matching->categories()->attach($category);
    $secondMatching = storefrontProduct('Acme Skin Cream', ['brand_id' => $brand->id, 'price' => 2800]);
    $secondMatching->categories()->attach($category);
    ProductImage::query()->create(['product_id' => $matching->id, 'path' => 'products/serum.png', 'alt' => 'Serum', 'is_primary' => true]);
    Storage::disk(ProductImage::storageDisk())->put('products/serum.png', 'image');
    storefrontProduct('Other Product', ['price' => 500]);
    storefrontProduct('Unavailable Product', ['price' => 2000, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
    storefrontProduct('Draft Product', ['status' => 'draft']);
    $deleted = storefrontProduct('Deleted Product');
    $deleted->delete();

    $this->get('/products?category=skin&brand=acme&in_stock=1&sort=price_desc&per_page=1')
        ->assertOk()
        ->assertSee('Acme Skin Serum')
        ->assertSee('3,000 ریال')
        ->assertSee('/storage/products/serum.png')
        ->assertSee('category-pagination')
        ->assertSee('category=skin')
        ->assertSee('page=2')
        ->assertDontSee('Other Product')
        ->assertDontSee('Draft Product')
        ->assertDontSee('inventory_transactions');

    $this->get('/products?search=does-not-exist')->assertOk()
        ->assertSee('محصولی پیدا نشد')
        ->assertSee('search');

    $this->get('/products?search=Acme&sort=name_asc')->assertOk()
        ->assertSeeInOrder(['Acme Skin Cream', 'Acme Skin Serum']);

    $this->get('/products?in_stock=0')->assertOk()
        ->assertSee('Unavailable Product')
        ->assertDontSee('Acme Skin Serum');
});

test('listing supports variable price range and stable query filters', function (): void {
    $fixture = storefrontVariableProduct();

    $this->get('/products?type=variable&min_price=1200&max_price=1400&sort=price_asc')
        ->assertOk()
        ->assertSee('Variable Storefront Product')
        ->assertSee('1,200 تا 1,400 ریال')
        ->assertSee('موجود');

    $this->get('/products?sort=price_desc')->assertOk()
        ->assertSeeInOrder(['Variable Storefront Product']);

    $this->get('/products?sort=popular')->assertRedirect();
    expect($fixture['product']->fresh()->status)->toBe('published');
});
