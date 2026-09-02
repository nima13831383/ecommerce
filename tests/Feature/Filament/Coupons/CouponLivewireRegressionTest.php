<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\Coupon;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function couponLivewireEditor(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $editor = User::factory()->create();
    $editor->givePermissionTo(collect(['coupons.view', 'coupons.create', 'coupons.update'])
        ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $editor;
}

it('creates all coupon types from Filament and reloads an integral float amount', function (string $type, int|float|string $amount): void {
    $editor = couponLivewireEditor();
    $code = 'LW-'.strtoupper(fake()->unique()->bothify('??##'));

    Livewire::actingAs($editor, 'web')
        ->test(CreateCoupon::class)
        ->fillForm([
            'code' => $code,
            'type' => $type,
            'amount' => $amount,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::query()->where('code', $code)->sole();

    expect($coupon->type)->toBe($type)->and($coupon->amount)->toBe(29);

    Livewire::actingAs($editor, 'web')
        ->test(EditCoupon::class, ['record' => $coupon->getKey()])
        ->fillForm(['amount' => 29.0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coupon->fresh()->amount)->toBe(29);
})->with([
    'percent integer' => ['percent', 29],
    'fixed cart integral float' => ['fixed_cart', 29.0],
    'fixed product numeric string' => ['fixed_product', '29'],
]);

it('rejects a fractional Rial amount from the Filament create flow', function (): void {
    $editor = couponLivewireEditor();
    $code = 'LW-FRACTION-'.fake()->unique()->numerify('####');

    Livewire::actingAs($editor, 'web')
        ->test(CreateCoupon::class)
        ->fillForm(['code' => $code, 'type' => 'fixed_cart', 'amount' => 29.1, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['amount']);

    expect(Coupon::query()->where('code', $code)->exists())->toBeFalse();
});
