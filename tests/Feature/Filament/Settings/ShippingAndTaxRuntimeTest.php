<?php

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\TaxClasses\Pages\CreateTaxClass;
use App\Filament\Resources\TaxClasses\Pages\EditTaxClass;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function shippingTaxRuntimeAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function shippingSetting(string $key, string $type, mixed $value = null): Setting
{
    return Setting::query()->firstOrCreate(
        ['group' => 'shipping', 'key' => $key],
        ['type' => $type, 'value' => $value],
    );
}

function saveRuntimeSetting(User $user, Setting $setting, mixed $value): void
{
    $field = match ($setting->type) {
        'string' => 'value_string',
        'json' => 'value_json',
        default => 'value_number',
    };

    Livewire::actingAs($user, 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm([$field => $value], 'form')
        ->call('save')
        ->assertHasNoFormErrors();
}

function assertRuntimeSettingInvalid(User $user, Setting $setting, mixed $value): void
{
    $field = match ($setting->type) {
        'string' => 'value_string',
        'json' => 'value_json',
        default => 'value_number',
    };

    Livewire::actingAs($user, 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm([$field => $value], 'form')
        ->call('save')
        ->assertHasFormErrors();
}

test('shipping settings use real Filament forms to persist calculator fixed and free modes', function (): void {
    $admin = shippingTaxRuntimeAdmin(['settings.view', 'settings.update']);
    $mode = shippingSetting('shipping.mode', 'string');
    $province = shippingSetting('shipping.origin_province_id', 'integer');
    $city = shippingSetting('shipping.origin_city_id', 'integer');
    $packages = shippingSetting('shipping.packages', 'json');
    $fixedRate = shippingSetting('shipping.fixed_rate_amount', 'money');
    $geography = app(WordpressShippingDataLoader::class);
    $provinceId = (int) array_key_first($geography->provinces());
    $cityId = (int) array_key_first($geography->cities($provinceId));

    expect($provinceId)->toBeGreaterThan(0)->and($cityId)->toBeGreaterThan(0);

    saveRuntimeSetting($admin, $mode, 'calculator');
    saveRuntimeSetting($admin, $province, $provinceId);
    saveRuntimeSetting($admin, $city, $cityId);
    saveRuntimeSetting($admin, $packages, json_encode([[
        'id' => 'runtime-small',
        'name' => 'Runtime small package',
        'capacity_volume' => 10.5,
        'max_weight' => 2.5,
        'code' => 1,
        'active' => true,
    ]], JSON_THROW_ON_ERROR));

    expect($mode->fresh()->typed_value)->toBe('calculator')
        ->and($province->fresh()->typed_value)->toBe($provinceId)
        ->and($city->fresh()->typed_value)->toBe($cityId)
        ->and($packages->fresh()->typed_value)->toMatchArray([['id' => 'runtime-small', 'name' => 'Runtime small package', 'capacity_volume' => 10.5, 'max_weight' => 2.5, 'code' => 1, 'active' => true]]);

    saveRuntimeSetting($admin, $fixedRate, 250000.0);
    saveRuntimeSetting($admin, $mode, 'fixed');
    expect($fixedRate->fresh()->typed_value)->toBe(250000)->and($mode->fresh()->typed_value)->toBe('fixed');

    saveRuntimeSetting($admin, $mode, 'free');
    expect($mode->fresh()->typed_value)->toBe('free');
});

test('shipping geography and packages reject invalid settings through the real edit page', function (): void {
    $admin = shippingTaxRuntimeAdmin(['settings.view', 'settings.update']);
    $province = shippingSetting('shipping.origin_province_id', 'integer');
    $city = shippingSetting('shipping.origin_city_id', 'integer');
    $packages = shippingSetting('shipping.packages', 'json');
    $geography = app(WordpressShippingDataLoader::class);
    $provinceId = (int) array_key_first($geography->provinces());
    saveRuntimeSetting($admin, $province, $provinceId);

    assertRuntimeSettingInvalid($admin, $city, 99999999);
    assertRuntimeSettingInvalid($admin, $province, 99999999);
    assertRuntimeSettingInvalid($admin, $packages, json_encode([['id' => 'bad', 'name' => 'Bad', 'capacity_volume' => 0, 'max_weight' => -1, 'code' => 999]], JSON_THROW_ON_ERROR));

    expect($city->fresh()->value)->toBeNull()
        ->and($province->fresh()->typed_value)->toBe($provinceId)
        ->and($packages->fresh()->typed_value)->toBe([]);
});

test('tax class Filament forms preserve valid precision and reject non-integral fixed Rials', function (): void {
    $admin = shippingTaxRuntimeAdmin(['tax-classes.view', 'tax-classes.create', 'tax-classes.update']);

    Livewire::actingAs($admin, 'web')
        ->test(CreateTaxClass::class)
        ->fillForm(['name' => 'Runtime percent', 'slug' => 'runtime-percent', 'type' => 'percent', 'value' => '9.125', 'is_active' => true], 'form')
        ->call('create')
        ->assertHasNoFormErrors();

    $percent = TaxClass::query()->where('slug', 'runtime-percent')->firstOrFail();
    expect($percent->fresh()->value)->toBe('9.125')->and($percent->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($admin, 'web')
        ->test(CreateTaxClass::class)
        ->fillForm(['name' => 'Runtime fixed', 'slug' => 'runtime-fixed', 'type' => 'fixed', 'value' => 10000.0, 'is_active' => false], 'form')
        ->call('create')
        ->assertHasNoFormErrors();

    $fixed = TaxClass::query()->where('slug', 'runtime-fixed')->firstOrFail();
    expect($fixed->fresh()->value)->toBe('10000.000')->and($fixed->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin, 'web')
        ->test(EditTaxClass::class, ['record' => $fixed->getRouteKey()])
        ->fillForm(['value' => '10000.5'], 'form')
        ->call('save')
        ->assertHasFormErrors(['value']);
});
