<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function adminUser(array $permissions = []): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo(collect($permissions)->map(
            fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
        ));
    }

    return $user;
}

function productRecord(): Product
{
    return Product::query()->create([
        'name' => 'Authorization test product',
        'slug' => 'authorization-test-product-'.fake()->unique()->numerify('###'),
    ]);
}

test('a guest cannot access the admin panel', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('an authenticated user without administrative authorization cannot access the admin panel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('a super-admin can access the panel and bypasses individual permissions', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $this->actingAs($superAdmin)
        ->get('/admin')
        ->assertOk();

    expect(Gate::forUser($superAdmin)->allows('create', Product::class))->toBeTrue();
});

test('an admin permission grants product listing but not product creation', function (): void {
    $user = adminUser(['products.view']);

    $this->actingAs($user)
        ->get('/admin/products')
        ->assertOk();

    $this->get('/admin/products/create')->assertForbidden();
});

test('missing update and delete permissions deny product operations', function (): void {
    $user = adminUser(['products.view']);
    $product = productRecord();

    $this->actingAs($user)
        ->get("/admin/products/{$product->id}/edit")
        ->assertForbidden();

    expect(Gate::forUser($user)->allows('update', $product))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $product))->toBeFalse();
});

test('restore and force-delete use distinct product permissions', function (): void {
    $user = adminUser(['products.delete']);
    $product = productRecord();
    $product->delete();

    expect(Gate::forUser($user)->allows('restore', $product))->toBeFalse()
        ->and(Gate::forUser($user)->allows('forceDelete', $product))->toBeFalse();

    $user->givePermissionTo([
        Permission::findOrCreate('products.restore', 'web'),
        Permission::findOrCreate('products.force-delete', 'web'),
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Gate::forUser($user->fresh())->allows('restore', $product))->toBeTrue()
        ->and(Gate::forUser($user->fresh())->allows('forceDelete', $product))->toBeTrue();
});

test('settings remain inaccessible without a settings permission', function (): void {
    $user = adminUser(['products.view']);

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertForbidden();

    $user->givePermissionTo(Permission::findOrCreate('settings.view', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())
        ->get('/admin/settings')
        ->assertOk();
});

test('a soft-deleted user cannot retain admin panel access', function (): void {
    $user = adminUser(['products.view']);
    $user->delete();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('the role and permission seeder does not promote existing or new users', function (): void {
    $existingUser = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);
    $newUser = User::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);

    expect($existingUser->fresh()->roles)->toBeEmpty()
        ->and($newUser->fresh()->roles)->toBeEmpty()
        ->and($existingUser->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($newUser->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($newUser)
        ->get('/admin')
        ->assertForbidden();
});

test('explicitly assigning the admin role grants Filament panel access', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole(Role::findByName('admin', 'web'));
    $administrator = $administrator->fresh();

    expect($administrator->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($administrator)
        ->get('/admin')
        ->assertOk();
});

test('the role and permission seeder is idempotent', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->whereIn('name', ['super-admin', 'admin'])->count())->toBe(2)
        ->and(Permission::query()->where('name', 'products.view')->count())->toBe(1)
        ->and(Role::findByName('super-admin')->hasPermissionTo('settings.update'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('products.create'))->toBeTrue();
});
