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

function apiProduct(string $name, array $attributes = []): Product
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

    if ($product->type !== 'variable' && (int) ($attributes['stock_quantity'] ?? 10) > 0) {
        app(InventoryService::class)->setOnHand($product, (int) ($attributes['stock_quantity'] ?? 10));
    }

    return $product;
}

function apiVariableProduct(string $name = 'Variable Product'): array
{
    $suffix = str($name)->slug();
    $product = apiProduct($name, [
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
    $first = $service->create($product, ['price' => 1200, 'stock_quantity' => 4, 'sku' => 'VAR-'.str($suffix)->upper().'-RED-S'], [$red->id, $small->id]);
    $second = $service->create($product, ['price' => 1400, 'stock_quantity' => 0, 'sku' => 'VAR-'.str($suffix)->upper().'-BLUE-L'], [$blue->id, $large->id]);

    return compact('product', 'color', 'size', 'red', 'blue', 'small', 'large', 'first', 'second');
}

test('public product listing exposes only published products with integer rial pricing and pagination metadata', function (): void {
    $published = apiProduct('Published Phone', ['price' => 2500, 'sale_price' => 1900, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);
    ProductImage::query()->create(['product_id' => $published->id, 'path' => 'products/phone.png', 'alt' => 'Phone', 'is_primary' => true]);
    Storage::disk(ProductImage::storageDisk())->put('products/phone.png', 'image');
    apiProduct('Another Published');
    apiProduct('Draft Phone', ['status' => 'draft']);
    apiProduct('Deleted Phone')->delete();

    $response = $this->getJson('/api/v1/products?per_page=1');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Another Published');

    $data = $response->json('data.0');
    expect($data)->not->toHaveKey('deleted_at')
        ->and($data['pricing']['effective_price'])->toBeInt()
        ->and($data['pricing']['regular_price'])->toBeInt()
        ->and($data['pricing']['currency'])->toBe('IRR');
});

test('listing filters and sorting use public stable parameters', function (): void {
    $category = Category::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
    $brand = Brand::query()->create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
    $matching = apiProduct('Acme Phone', ['brand_id' => $brand->id, 'price' => 3000]);
    $matching->categories()->attach($category);
    apiProduct('Out of stock', ['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);
    apiProduct('Cheap Phone', ['price' => 500]);

    $this->getJson('/api/v1/products?search=Acme')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/products?category=phones&brand=acme')->assertJsonPath('data.0.id', $matching->id);
    $this->getJson('/api/v1/products?in_stock=0')->assertJsonFragment(['name' => 'Out of stock']);
    $this->getJson('/api/v1/products?min_price=2000&sort=price_desc')->assertJsonPath('data.0.id', $matching->id);
    $this->getJson('/api/v1/products?sort=name_asc')->assertOk();

    $this->getJson('/api/v1/products?sort=popular')->assertStatus(422)->assertJsonPath('code', 'validation_error');
    $this->getJson('/api/v1/products?min_price=10&max_price=1')->assertStatus(422)->assertJsonPath('code', 'validation_error');
    $this->getJson('/api/v1/products?min_price=1.5')->assertStatus(422)->assertJsonPath('code', 'validation_error');
});

test('variable listing uses resolver-backed price range and availability', function (): void {
    $fixture = apiVariableProduct('Range Variable');

    $response = $this->getJson('/api/v1/products?type=variable&in_stock=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $fixture['product']->id)
        ->assertJsonPath('data.0.pricing.minimum_price', 1200)
        ->assertJsonPath('data.0.pricing.maximum_price', 1400)
        ->assertJsonPath('data.0.availability.in_stock', true);
});

test('simple and variable product detail expose public gallery taxonomy attributes and variation data', function (): void {
    $simple = apiProduct('Simple Detail', ['short_description' => 'Short', 'description' => 'Full description']);
    ProductImage::query()->create(['product_id' => $simple->id, 'path' => 'products/detail.png', 'alt' => 'Detail', 'is_primary' => true]);
    Storage::disk(ProductImage::storageDisk())->put('products/detail.png', 'image');
    $variable = apiVariableProduct();

    $simpleResponse = $this->getJson('/api/v1/products/'.$simple->slug);
    $simpleResponse->assertOk()
        ->assertJsonPath('data.slug', $simple->slug)
        ->assertJsonPath('data.description', 'Full description')
        ->assertJsonPath('data.gallery.0.url', '/storage/products/detail.png');

    $variableResponse = $this->getJson('/api/v1/products/'.$variable['product']->slug);
    $variableResponse->assertOk()
        ->assertJsonCount(2, 'data.attributes')
        ->assertJsonCount(2, 'data.variations')
        ->assertJsonPath('data.variations.0.pricing.currency', 'IRR');

    expect($variableResponse->json())->not->toHaveKey('data.inventory_transactions')
        ->and($variableResponse->json())->not->toHaveKey('data.deleted_at');
});

test('variation resolution is authoritative, order independent, and rejects invalid selections cleanly', function (): void {
    $fixture = apiVariableProduct();
    $product = $fixture['product'];
    $variation = $fixture['first'];

    $response = $this->postJson('/api/v1/products/'.$product->id.'/resolve-variation', [
        'options' => [
            ['attribute_id' => $fixture['size']->id, 'value_id' => $fixture['small']->id],
            ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['red']->id],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $variation->id)
        ->assertJsonPath('data.pricing.effective_price', 1200)
        ->assertJsonPath('data.availability.in_stock', true);
    expect($response->json('data.pricing.effective_price'))->toBeInt();

    $this->postJson('/api/v1/products/'.$product->id.'/resolve-variation', ['options' => [
        ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['red']->id],
        ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['blue']->id],
    ]])->assertStatus(422)->assertJsonPath('code', 'duplicate_attribute');

    $this->postJson('/api/v1/products/'.$product->id.'/resolve-variation', ['options' => [
        ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['red']->id],
    ]])->assertStatus(422)->assertJsonPath('code', 'variation_invalid');

    $other = apiVariableProduct('Other Variable');
    $this->postJson('/api/v1/products/'.$product->id.'/resolve-variation', ['options' => [
        ['attribute_id' => $other['color']->id, 'value_id' => $other['red']->id],
        ['attribute_id' => $fixture['size']->id, 'value_id' => $fixture['small']->id],
    ]])->assertStatus(422)->assertJsonPath('code', 'invalid_attribute');

    $simple = apiProduct('Simple Resolve');
    $this->postJson('/api/v1/products/'.$simple->id.'/resolve-variation', ['options' => [
        ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['red']->id],
    ]])->assertStatus(422)->assertJsonPath('code', 'product_not_variable');

    $variation->update(['is_active' => false]);
    $this->postJson('/api/v1/products/'.$product->id.'/resolve-variation', ['options' => [
        ['attribute_id' => $fixture['color']->id, 'value_id' => $fixture['red']->id],
        ['attribute_id' => $fixture['size']->id, 'value_id' => $fixture['small']->id],
    ]])->assertStatus(422)->assertJsonPath('code', 'variation_unavailable');
});

test('inactive and deleted products are not publicly addressable', function (): void {
    $inactive = apiProduct('Inactive', ['status' => 'draft']);
    $deleted = apiProduct('Deleted');
    $deleted->delete();

    $this->getJson('/api/v1/products/'.$inactive->slug)->assertNotFound();
    $this->getJson('/api/v1/products/'.$deleted->slug)->assertNotFound();
    $this->getJson('/api/v1/products/not-real')->assertNotFound()->assertJsonPath('code', 'not_found');
});
