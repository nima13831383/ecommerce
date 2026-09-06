<?php

use App\Filament\Pages\ManageStorefrontCache;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('cache management page is limited to authorized administrators', function (): void {
    $customer = User::factory()->create();
    $manager = User::factory()->create();
    $manager->givePermissionTo(Permission::findOrCreate('settings.update', 'web'));
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    expect(Gate::forUser($customer)->allows('viewHorizon'))->toBeFalse()
        ->and(ManageStorefrontCache::canAccess())->toBeFalse();

    Livewire::actingAs($customer, 'web')
        ->test(ManageStorefrontCache::class)
        ->assertForbidden();
    $this->actingAs($customer)->get('/horizon')->assertForbidden();

    $this->actingAs($manager);
    expect(ManageStorefrontCache::canAccess())->toBeTrue()
        ->and(Gate::forUser($manager)->allows('viewHorizon'))->toBeTrue();

    $this->actingAs($superAdmin);
    expect(ManageStorefrontCache::canAccess())->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewHorizon'))->toBeTrue();

    Livewire::actingAs($superAdmin, 'web')
        ->test(ManageStorefrontCache::class)
        ->assertOk()
        ->assertSee('مدیریت کش و صف‌ها')
        ->assertSee('Horizon نیازمند Redis Queue است و در محیط فعلی فعال نیست.');
});
