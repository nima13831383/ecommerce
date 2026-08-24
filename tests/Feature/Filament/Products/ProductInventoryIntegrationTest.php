<?php

use App\Enums\InventoryOperation;
use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function productInventoryAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super-admin', 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('products.create', 'web'),
        Permission::findOrCreate('products.update', 'web'),
    ]);

    return $user;
}

function simpleProductData(string $slug, int $stockQuantity, array $overrides = []): array
{
    return array_replace([
        'type' => 'simple',
        'name' => 'محصول تستی',
        'slug' => $slug,
        'price' => 100000,
        'manage_stock' => true,
        'stock_quantity' => $stockQuantity,
        'stock_status' => 'in_stock',
        'status' => 'draft',
        'images' => [],
    ], $overrides);
}

function inventoryTransactionFor(Product|ProductVariation $owner, int $offset = 0): InventoryTransaction
{
    return InventoryTransaction::query()
        ->where('inventory_owner_type', $owner::class)
        ->where('inventory_owner_id', $owner->id)
        ->latest('id')
        ->skip($offset)
        ->firstOrFail();
}

function createFilamentSimpleProduct(User $user, int $stockQuantity = 10): Product
{
    $slug = 'filament-simple-'.str()->uuid();

    auth()->login($user);
    Filament::auth()->login($user);

    expect(Gate::forUser($user)->allows('create', Product::class))->toBeTrue();
    expect(ProductResource::canCreate())->toBeTrue();

    Livewire::actingAs($user, 'web')
        ->test(CreateProduct::class)
        ->assertOk()
        ->fillForm(simpleProductData($slug, $stockQuantity), 'form')
        ->call('create')
        ->assertHasNoFormErrors();

    return Product::query()->where('slug', $slug)->firstOrFail();
}

function saveFilamentVariation(User $user, Product $product, Attribute $attribute, AttributeValue $value, ProductVariation $variation, int $stockQuantity, int $price): void
{
    Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->set('data.variation_attributes', [
            (string) str()->uuid() => [
                'attribute_id' => $attribute->id,
                'value_ids' => [$value->id],
            ],
        ])
        ->set('data.variations', [
            (string) str()->uuid() => [
                'id' => $variation->id,
                'attribute_value_ids' => (string) $value->id,
                'sku' => $variation->sku,
                'price' => $price,
                'sale_price' => null,
                'stock_quantity' => $stockQuantity,
                'is_active' => true,
                'is_dismissed' => false,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();
}

test('the Filament product create page establishes opening stock through the inventory ledger', function (): void {
    $product = createFilamentSimpleProduct(productInventoryAdmin());

    $transaction = inventoryTransactionFor($product);

    expect($product->stock_quantity)->toBe(10)
        ->and($product->stock_status)->toBe('in_stock')
        ->and(InventoryTransaction::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(1)
        ->and($transaction->operation)->toBe(InventoryOperation::OpeningStock)
        ->and($transaction->quantity_delta)->toBe(10)
        ->and($transaction->quantity_before)->toBe(0)
        ->and($transaction->quantity_after)->toBe(10);
});

test('the Filament product edit page records positive and negative stock deltas', function (): void {
    $user = productInventoryAdmin();
    $product = createFilamentSimpleProduct($user);
    $this->actingAs($user);

    Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['stock_quantity' => 15], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    $increase = inventoryTransactionFor($product);

    expect($product->fresh()->stock_quantity)->toBe(15)
        ->and($increase->operation)->toBe(InventoryOperation::ManualAdjustment)
        ->and($increase->quantity_delta)->toBe(5)
        ->and($increase->quantity_before)->toBe(10)
        ->and($increase->quantity_after)->toBe(15);

    Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['stock_quantity' => 7], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    $reduction = inventoryTransactionFor($product);

    expect($product->fresh()->stock_quantity)->toBe(7)
        ->and($reduction->operation)->toBe(InventoryOperation::ManualAdjustment)
        ->and($reduction->quantity_delta)->toBe(-8)
        ->and($reduction->quantity_before)->toBe(15)
        ->and($reduction->quantity_after)->toBe(7);
});

test('a Filament product metadata-only edit creates no inventory ledger noise', function (): void {
    $user = productInventoryAdmin();
    $product = createFilamentSimpleProduct($user);
    $this->actingAs($user);

    Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm([
            'name' => 'نام ویرایش‌شده',
            'stock_quantity' => 10,
        ], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->name)->toBe('نام ویرایش‌شده')
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and(InventoryTransaction::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(1);
});

test('a reservation-protected Filament product edit rolls back metadata and inventory changes', function (): void {
    $user = productInventoryAdmin();
    $product = createFilamentSimpleProduct($user);
    $reservation = app(InventoryService::class)->reserve($product, 8, now()->addHour(), 'test', 'simple-edit');
    $this->actingAs($user);

    $component = Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm([
            'name' => 'نباید ذخیره شود',
            'stock_quantity' => 5,
        ], 'form');

    expect(fn () => $component->call('save'))->toThrow(InsufficientStockException::class);

    expect($product->fresh()->name)->toBe('محصول تستی')
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and($reservation->fresh()->status->value)->toBe('active')
        ->and(InventoryTransaction::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(1);
});

test('the Filament variable-product path writes stock only for the variation', function (): void {
    $user = productInventoryAdmin();
    $attribute = Attribute::create(['name' => 'رنگ', 'slug' => 'color-'.str()->uuid(), 'is_variation' => true]);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'قرمز', 'slug' => 'red-'.str()->uuid()]);
    $slug = 'filament-variable-'.str()->uuid();
    $attributeKey = (string) str()->uuid();
    $variationKey = (string) str()->uuid();

    $this->actingAs($user);

    $component = Livewire::actingAs($user, 'web')
        ->test(CreateProduct::class)
        ->fillForm(['type' => 'variable'], 'form')
        ->fillForm([
            'name' => 'محصول متغیر تستی',
            'slug' => $slug,
            'manage_stock' => false,
            'status' => 'draft',
            'images' => [],
        ], 'form');

    $component
        ->set('data.variation_attributes', [
            $attributeKey => [
                'attribute_id' => $attribute->id,
                'value_ids' => [$value->id],
            ],
        ])
        ->set('data.variations', [
            $variationKey => [
                'attribute_value_ids' => (string) $value->id,
                'sku' => 'FILAMENT-VARIANT-'.str()->upper(str()->random(8)),
                'price' => 150000,
                'sale_price' => null,
                'stock_quantity' => 6,
                'is_active' => true,
                'is_dismissed' => false,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', $slug)->firstOrFail();
    $variation = $product->variations()->firstOrFail();
    $transaction = inventoryTransactionFor($variation);

    expect($product->stock_quantity)->toBe(0)
        ->and(InventoryTransaction::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(0)
        ->and($variation->combination_signature)->toBe("{$attribute->id}:{$value->id}")
        ->and($variation->stock_quantity)->toBe(6)
        ->and($variation->stock_status)->toBe('in_stock')
        ->and($transaction->inventory_owner_type)->toBe(ProductVariation::class)
        ->and($transaction->inventory_owner_id)->toBe($variation->id)
        ->and($transaction->operation)->toBe(InventoryOperation::OpeningStock)
        ->and($transaction->quantity_delta)->toBe(6)
        ->and($transaction->quantity_before)->toBe(0)
        ->and($transaction->quantity_after)->toBe(6);
});

test('the Filament variable-product edit page records variant adjustments without touching parent stock', function (): void {
    $user = productInventoryAdmin();
    $attribute = Attribute::create(['name' => 'سایز', 'slug' => 'size-'.str()->uuid(), 'is_variation' => true]);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'بزرگ', 'slug' => 'large-'.str()->uuid()]);
    $product = Product::create(['type' => 'variable', 'name' => 'متغیر', 'slug' => 'variable-'.str()->uuid()]);
    $product->attributes()->sync([$attribute->id => ['sort_order' => 0]]);
    $product->attributeValues()->sync([$value->id]);
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'combination_signature' => "{$attribute->id}:{$value->id}",
        'price' => 150000,
        'sku' => 'EDIT-VARIANT-'.str()->upper(str()->random(8)),
    ]);
    app(InventoryService::class)->setOnHand($variation, 6, InventoryOperation::OpeningStock);
    $this->actingAs($user);

    saveFilamentVariation($user, $product, $attribute, $value, $variation, 9, 175000);

    $adjustment = inventoryTransactionFor($variation);

    expect($product->fresh()->stock_quantity)->toBe(0)
        ->and($variation->fresh()->stock_quantity)->toBe(9)
        ->and($variation->fresh()->price)->toBe('175000')
        ->and($adjustment->operation)->toBe(InventoryOperation::ManualAdjustment)
        ->and($adjustment->quantity_delta)->toBe(3)
        ->and($adjustment->quantity_before)->toBe(6)
        ->and($adjustment->quantity_after)->toBe(9);

    saveFilamentVariation($user, $product, $attribute, $value, $variation, 4, 175000);

    $reduction = inventoryTransactionFor($variation);

    expect($variation->fresh()->stock_quantity)->toBe(4)
        ->and($reduction->quantity_delta)->toBe(-5)
        ->and($reduction->quantity_before)->toBe(9)
        ->and($reduction->quantity_after)->toBe(4);

    $transactionCount = InventoryTransaction::query()
        ->where('inventory_owner_type', ProductVariation::class)
        ->where('inventory_owner_id', $variation->id)
        ->count();

    saveFilamentVariation($user, $product, $attribute, $value, $variation, 4, 190000);

    expect($variation->fresh()->stock_quantity)->toBe(4)
        ->and($variation->fresh()->stock_status)->toBe('in_stock')
        ->and($variation->fresh()->price)->toBe('190000')
        ->and(InventoryTransaction::query()->where('inventory_owner_type', ProductVariation::class)->where('inventory_owner_id', $variation->id)->count())->toBe($transactionCount);
});

test('a reservation-protected Filament variant edit rolls back variant metadata and inventory changes', function (): void {
    $user = productInventoryAdmin();
    $attribute = Attribute::create(['name' => 'جنس', 'slug' => 'material-'.str()->uuid(), 'is_variation' => true]);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'پنبه', 'slug' => 'cotton-'.str()->uuid()]);
    $product = Product::create(['type' => 'variable', 'name' => 'متغیر رزرو', 'slug' => 'reserved-variable-'.str()->uuid()]);
    $product->attributes()->sync([$attribute->id => ['sort_order' => 0]]);
    $product->attributeValues()->sync([$value->id]);
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'combination_signature' => "{$attribute->id}:{$value->id}",
        'price' => 150000,
        'sku' => 'RESERVED-VARIANT-'.str()->upper(str()->random(8)),
    ]);
    app(InventoryService::class)->setOnHand($variation, 10, InventoryOperation::OpeningStock);
    $reservation = app(InventoryService::class)->reserve($variation, 8, now()->addHour(), 'test', 'variant-edit');
    $this->actingAs($user);

    $component = Livewire::actingAs($user, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->set('data.variation_attributes', [
            (string) str()->uuid() => [
                'attribute_id' => $attribute->id,
                'value_ids' => [$value->id],
            ],
        ])
        ->set('data.variations', [
            (string) str()->uuid() => [
                'id' => $variation->id,
                'attribute_value_ids' => (string) $value->id,
                'sku' => $variation->sku,
                'price' => 175000,
                'sale_price' => null,
                'stock_quantity' => 5,
                'is_active' => true,
                'is_dismissed' => false,
            ],
        ]);

    expect(fn () => $component->call('save'))->toThrow(InsufficientStockException::class);

    expect($variation->fresh()->stock_quantity)->toBe(10)
        ->and($variation->fresh()->price)->toBe('150000')
        ->and($reservation->fresh()->status->value)->toBe('active')
        ->and(InventoryTransaction::query()->where('inventory_owner_type', ProductVariation::class)->where('inventory_owner_id', $variation->id)->count())->toBe(1);
});
