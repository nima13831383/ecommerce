<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\ExpectationFailedException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake(ProductImage::storageDisk());
});

function productMediaUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function productMediaProduct(string $suffix): Product
{
    return Product::query()->create([
        'name' => "Media {$suffix}",
        'slug' => "media-{$suffix}",
        'type' => 'simple',
        'price' => 10000,
        'status' => 'draft',
    ]);
}

function productMediaManager(Product $product, User $user)
{
    return Livewire::actingAs($user, 'web')->test(ImagesRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

test('the registered Images relation manager uploads reloads edits reorders and deletes isolated files', function (): void {
    $admin = productMediaUser(['products.view', 'products.update']);
    $product = productMediaProduct('authorized');

    expect(ProductResource::getRelations())->toContain(ImagesRelationManager::class);

    productMediaManager($product, $admin)
        ->callTableAction('create', data: [
            'path' => UploadedFile::fake()->image('first.png', 20, 20),
            'alt' => 'First image',
            'is_primary' => true,
            'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    $first = $product->images()->sole();
    expect($first->product_id)->toBe($product->id)
        ->and($first->path)->toStartWith('products/')
        ->and($first->alt)->toBe('First image')
        ->and($first->is_primary)->toBeTrue();
    Storage::disk(ProductImage::storageDisk())->assertExists($first->path);

    productMediaManager($product, $admin)
        ->callTableAction('create', data: [
            'path' => UploadedFile::fake()->image('second.jpg', 20, 20),
            'alt' => 'Second image',
            'is_primary' => true,
            'sort_order' => 2,
        ])
        ->assertHasNoTableActionErrors();

    $second = $product->images()->where('id', '!=', $first->id)->sole();
    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->is_primary)->toBeTrue();

    productMediaManager($product, $admin)->call('reorderTable', [$second->id, $first->id]);
    expect($second->fresh()->sort_order)->toBe(1)->and($first->fresh()->sort_order)->toBe(2);

    productMediaManager($product, $admin)
        ->callTableAction('edit', $second, data: ['alt' => 'Edited alt', 'is_primary' => true, 'sort_order' => 0])
        ->assertHasNoTableActionErrors();
    expect($second->fresh()->alt)->toBe('Edited alt')->and($second->fresh()->sort_order)->toBe(0);

    $path = $second->path;
    productMediaManager($product, $admin)->callTableAction('delete', $second);
    expect(ProductImage::query()->find($second->id))->toBeNull();
    Storage::disk(ProductImage::storageDisk())->assertMissing($path);
});

test('the actual image upload field rejects non-images and does not persist an image row', function (): void {
    $admin = productMediaUser(['products.view', 'products.update']);
    $product = productMediaProduct('validation');

    productMediaManager($product, $admin)
        ->callTableAction('create', data: [
            'path' => UploadedFile::fake()->create('not-image.txt', 10, 'text/plain'),
            'alt' => 'Invalid',
            'sort_order' => 0,
        ])
        ->assertHasTableActionErrors(['path']);

    productMediaManager($product, $admin)
        ->callTableAction('create', data: [
            'path' => UploadedFile::fake()->create('spoofed.png', 10, 'text/plain'),
            'alt' => 'Spoofed image',
            'sort_order' => 0,
        ])
        ->assertHasTableActionErrors(['path']);

    expect($product->images()->count())->toBe(0);
});

test('a user without product update permission cannot mutate an images relation manager directly', function (): void {
    $viewer = productMediaUser(['products.view']);
    $product = productMediaProduct('unauthorized');

    $manager = productMediaManager($product, $viewer)->assertTableActionHidden('create');

    expect(fn () => $manager->callTableAction('create', data: [
        'path' => UploadedFile::fake()->image('blocked.png', 20, 20),
        'alt' => 'Blocked',
        'sort_order' => 0,
    ]))->toThrow(ExpectationFailedException::class);

    expect($product->images()->count())->toBe(0);
});

test('crafted edit and delete actions respect the parent product and cannot cross product boundaries', function (): void {
    $admin = productMediaUser(['products.view', 'products.update']);
    $viewer = productMediaUser(['products.view']);
    $productA = productMediaProduct('owner-a');
    $productB = productMediaProduct('owner-b');
    Storage::disk(ProductImage::storageDisk())->put('products/owner-b.png', 'test-image');
    $imageB = ProductImage::query()->create(['product_id' => $productB->id, 'path' => 'products/owner-b.png', 'alt' => 'B', 'sort_order' => 0]);

    $viewerManager = productMediaManager($productB, $viewer)
        ->assertTableActionHidden('edit', $imageB)
        ->assertTableActionHidden('delete', $imageB);
    expect(fn () => $viewerManager->callTableAction('edit', $imageB, data: ['alt' => 'Blocked']))->toThrow(ExpectationFailedException::class);
    expect(fn () => $viewerManager->callTableAction('delete', $imageB))->toThrow(ExpectationFailedException::class);
    expect($imageB->fresh()->alt)->toBe('B')->and(ProductImage::query()->find($imageB->id))->not->toBeNull();

    expect(fn () => productMediaManager($productA, $admin)->callTableAction('edit', $imageB, data: ['alt' => 'Cross product']))->toThrow(TypeError::class);
    expect($imageB->fresh()->alt)->toBe('B')->and($imageB->fresh()->product_id)->toBe($productB->id);

    $imageA = ProductImage::query()->create(['product_id' => $productA->id, 'path' => 'products/owner-a.png', 'alt' => 'A', 'sort_order' => 0]);
    productMediaManager($productA, $admin)->call('reorderTable', [$imageA->id, $imageB->id]);
    expect($imageB->fresh()->sort_order)->toBe(0);
});
