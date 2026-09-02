<?php

use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\Coupons\RelationManagers\RolesRelationManager;
use App\Filament\Resources\Coupons\RelationManagers\UsersRelationManager;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function couponTargetingEditor(): User
{
    $editor = User::factory()->create();
    $editor->givePermissionTo(collect(['coupons.view', 'coupons.update'])
        ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $editor;
}

function targetingCoupon(): Coupon
{
    return Coupon::query()->create(['code' => 'TARGET-'.fake()->unique()->numerify('####'), 'type' => 'fixed_cart', 'amount' => 10]);
}

function targetingProduct(): Product
{
    return Product::query()->create([
        'name' => 'Target '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => 'simple',
        'status' => 'published',
        'price' => 100,
    ]);
}

function targetingManager(string $manager, Coupon $coupon, User $editor): Testable
{
    return Livewire::actingAs($editor, 'web')->test($manager, [
        'ownerRecord' => $coupon,
        'pageClass' => EditCoupon::class,
    ]);
}

it('allows product inclusion and blocks a mixed product targeting action', function (): void {
    $coupon = targetingCoupon();
    $editor = couponTargetingEditor();
    $included = targetingProduct();
    $excluded = targetingProduct();
    $manager = targetingManager(ProductsRelationManager::class, $coupon, $editor);

    $manager->callTableAction('attach', data: ['recordId' => [$included->id], 'is_excluded' => false]);
    expect((bool) $coupon->products()->whereKey($included)->first()->pivot->is_excluded)->toBeFalse();

    $manager->callTableAction('attach', data: ['recordId' => [$excluded->id], 'is_excluded' => true]);
    expect($coupon->products()->whereKey($excluded)->exists())->toBeFalse();
});

it('allows user exclusion and blocks a mixed user targeting action', function (): void {
    $coupon = targetingCoupon();
    $editor = couponTargetingEditor();
    $excluded = User::factory()->create();
    $included = User::factory()->create();
    $manager = targetingManager(UsersRelationManager::class, $coupon, $editor);

    $manager->callTableAction('attach', data: ['recordId' => [$excluded->id], 'is_excluded' => true]);
    expect((bool) $coupon->users()->whereKey($excluded)->first()->pivot->is_excluded)->toBeTrue();

    $manager->callTableAction('attach', data: ['recordId' => [$included->id], 'is_excluded' => false]);
    expect($coupon->users()->whereKey($included)->exists())->toBeFalse();
});

it('allows role inclusion and blocks a mixed role targeting action while allowing cross-dimension targeting', function (): void {
    $coupon = targetingCoupon();
    $editor = couponTargetingEditor();
    $included = Role::findOrCreate('include-'.fake()->unique()->word(), 'web');
    $excluded = Role::findOrCreate('exclude-'.fake()->unique()->word(), 'web');
    $product = targetingProduct();

    targetingManager(ProductsRelationManager::class, $coupon, $editor)
        ->callTableAction('attach', data: ['recordId' => [$product->id], 'is_excluded' => false]);

    $manager = targetingManager(RolesRelationManager::class, $coupon, $editor);
    $manager->callTableAction('attach', data: ['recordId' => [$included->id], 'is_excluded' => false]);
    expect((bool) $coupon->roles()->whereKey($included)->first()->pivot->is_excluded)->toBeFalse();

    $manager->callTableAction('attach', data: ['recordId' => [$excluded->id], 'is_excluded' => true]);
    expect($coupon->roles()->whereKey($excluded)->exists())->toBeFalse();
});
