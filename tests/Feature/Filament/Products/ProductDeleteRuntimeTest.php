<?php

use App\Enums\InventoryOperation;
use App\Enums\OrderStatus;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake(ProductImage::storageDisk());
});

function productDeleteUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function productForDelete(string $suffix, string $type = 'simple'): Product
{
    return Product::query()->create([
        'name' => "Delete {$suffix}",
        'slug' => "delete-{$suffix}",
        'type' => $type,
        'price' => 10000,
        'status' => 'draft',
    ]);
}

function productDeletePage(Product $product, User $user)
{
    return Livewire::actingAs($user, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()]);
}

function attachDeleteImage(Product $product, string $name): ProductImage
{
    $path = "products/{$name}.png";
    Storage::disk(ProductImage::storageDisk())->put($path, 'isolated-image');

    return ProductImage::query()->create(['product_id' => $product->id, 'path' => $path, 'alt' => $name]);
}

test('actual Filament delete and restore preserve soft-deleted product media and variations', function (): void {
    $admin = productDeleteUser(['products.view', 'products.update', 'products.delete', 'products.restore']);
    $product = productForDelete('soft', 'variable');
    $variation = ProductVariation::query()->create(['product_id' => $product->id, 'combination_signature' => '1:1', 'sku' => 'DELETE-SOFT', 'price' => 10000]);
    $imageA = attachDeleteImage($product, 'soft-a');
    $imageB = attachDeleteImage($product, 'soft-b');

    productDeletePage($product, $admin)->mountAction('delete')->callMountedAction();

    expect(Product::query()->find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id)->deleted_at)->not->toBeNull()
        ->and(ProductImage::query()->where('product_id', $product->id)->count())->toBe(2)
        ->and(ProductVariation::query()->find($variation->id))->not->toBeNull();
    Storage::disk(ProductImage::storageDisk())->assertExists($imageA->path);
    Storage::disk(ProductImage::storageDisk())->assertExists($imageB->path);

    productDeletePage($product->fresh(), $admin)->mountAction('restore')->callMountedAction();

    expect($product->fresh()->trashed())->toBeFalse()
        ->and($product->images()->count())->toBe(2)
        ->and($product->variations()->count())->toBe(1);
    Storage::disk(ProductImage::storageDisk())->assertExists($imageA->path);
    Storage::disk(ProductImage::storageDisk())->assertExists($imageB->path);
});

test('actual Filament force delete cleans cascaded image files and rows', function (): void {
    $admin = productDeleteUser(['products.view', 'products.update', 'products.delete', 'products.force-delete']);
    $product = productForDelete('force');
    $imageA = attachDeleteImage($product, 'force-a');
    $imageB = attachDeleteImage($product, 'force-b');

    productDeletePage($product, $admin)->mountAction('delete')->callMountedAction();
    productDeletePage($product->fresh(), $admin)->mountAction('forceDelete')->callMountedAction();

    expect(Product::withTrashed()->find($product->id))->toBeNull()
        ->and(ProductImage::query()->where('product_id', $product->id)->count())->toBe(0);
    Storage::disk(ProductImage::storageDisk())->assertMissing($imageA->path);
    Storage::disk(ProductImage::storageDisk())->assertMissing($imageB->path);
});

test('active reservations remain resolvable after soft delete and block force delete until released', function (): void {
    $admin = productDeleteUser(['products.view', 'products.update', 'products.delete', 'products.force-delete']);
    $product = productForDelete('reserved');
    app(InventoryService::class)->setOnHand($product, 5, InventoryOperation::OpeningStock);
    $reservation = app(InventoryService::class)->reserve($product, 1, now()->addHour(), 'test', 'delete-reservation');

    productDeletePage($product, $admin)->mountAction('delete')->callMountedAction();
    expect($product->fresh()->trashed())->toBeTrue()
        ->and($reservation->fresh()->status->value)->toBe('active')
        ->and($reservation->fresh()->inventoryOwner)->not->toBeNull();

    productDeletePage($product->fresh(), $admin)->assertActionHidden('forceDelete');

    app(InventoryService::class)->release($reservation);
    expect(InventoryTransaction::query()->where('inventory_owner_id', $product->id)->exists())->toBeTrue();

    productDeletePage($product->fresh(), $admin)->mountAction('forceDelete')->callMountedAction();
    expect(Product::withTrashed()->find($product->id))->toBeNull()
        ->and(InventoryTransaction::query()->where('inventory_owner_id', $product->id)->count())->toBeGreaterThan(0);
});

test('crafted product delete is unavailable without delete permission', function (): void {
    $viewer = productDeleteUser(['products.view', 'products.update']);
    $product = productForDelete('authorization');
    $page = productDeletePage($product, $viewer)->assertActionHidden('delete');

    $page->mountAction('delete')->callMountedAction();
    expect($product->fresh()->trashed())->toBeFalse();
});

test('a Filament soft delete preserves OrderItem snapshots and released reservation history', function (): void {
    $admin = productDeleteUser(['products.view', 'products.update', 'products.delete']);
    $product = productForDelete('ordered');
    app(InventoryService::class)->setOnHand($product, 3, InventoryOperation::OpeningStock);
    $orders = app(OrderService::class);
    $order = $orders->create([['product_id' => $product->id, 'quantity' => 1]], ['customer_name' => 'Historic customer', 'customer_mobile' => '09120000000']);
    $item = $order->items()->sole();
    $orders->transitionStatus($order, OrderStatus::Cancelled);

    productDeletePage($product, $admin)->mountAction('delete')->callMountedAction();

    expect($item->fresh()->product_id)->toBe($product->id)
        ->and($item->fresh()->product_name)->toBe('Delete ordered')
        ->and($item->fresh()->unit_price)->toBe(10000)
        ->and($item->inventoryReservation->fresh()->status->value)->toBe('released')
        ->and($product->fresh()->trashed())->toBeTrue();
});
