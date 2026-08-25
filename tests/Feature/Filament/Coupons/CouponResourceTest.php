<?php

use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function couponFilamentAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $user;
}

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('coupon administration is permission protected and has no force-delete page', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin/coupons')->assertForbidden();

    $admin = couponFilamentAdmin(['coupons.view']);
    $coupon = Coupon::query()->create(['code' => 'FILAMENT-READ', 'type' => 'fixed_cart', 'amount' => 10]);

    $this->actingAs($admin)->get('/admin/coupons')->assertOk();

    Livewire::actingAs($admin, 'web')
        ->test(ListCoupons::class)
        ->assertCanSeeTableRecords([$coupon]);

    expect(CouponResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(CouponResource::getPages())->not->toHaveKey('view')
        ->and(CouponResource::getPages())->not->toHaveKey('force-delete');
});

test('coupon create and update permissions remain distinct from read access', function (): void {
    $viewer = couponFilamentAdmin(['coupons.view']);
    $editor = couponFilamentAdmin(['coupons.view', 'coupons.create', 'coupons.update']);

    $this->actingAs($viewer)->get('/admin/coupons/create')->assertForbidden();
    $this->actingAs($editor)->get('/admin/coupons/create')->assertOk();

    $coupon = Coupon::query()->create(['code' => 'FILAMENT-EDIT', 'type' => 'fixed_cart', 'amount' => 10]);
    $this->actingAs($viewer)->get('/admin/coupons/'.$coupon->id.'/edit')->assertForbidden();
    $this->actingAs($editor)->get('/admin/coupons/'.$coupon->id.'/edit')->assertOk();
});

test('coupon soft deletion preserves usage history and can be restored', function (): void {
    $admin = couponFilamentAdmin(['coupons.view', 'coupons.delete', 'coupons.update']);
    $coupon = Coupon::query()->create(['code' => 'FILAMENT-TRASH', 'type' => 'fixed_cart', 'amount' => 10]);

    $coupon->delete();

    expect(Coupon::query()->whereKey($coupon)->exists())->toBeFalse()
        ->and(Coupon::withTrashed()->whereKey($coupon)->exists())->toBeTrue();

    $coupon->restore();
    expect(Coupon::query()->whereKey($coupon)->exists())->toBeTrue();
});
