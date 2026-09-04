<?php

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function settingsAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $user;
}

test('settings are read/update protected and have no arbitrary create or delete paths', function (): void {
    $viewer = settingsAdmin(['settings.view']);
    $editor = settingsAdmin(['settings.view', 'settings.update']);
    $setting = Setting::query()->updateOrCreate(
        ['group' => 'tax', 'key' => 'default_tax_class_id'],
        ['type' => 'integer', 'value' => null],
    );

    $this->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/settings')->assertOk();
    $this->actingAs($viewer)->get('/admin/settings/'.$setting->id.'/edit')->assertForbidden();

    expect(SettingResource::getPages())->toHaveKeys(['index', 'edit'])
        ->and(SettingResource::getPages())->not->toHaveKey('create')
        ->and(SettingResource::getPages())->not->toHaveKey('delete');

    $this->actingAs($editor)->get('/admin/settings')->assertOk();
});

test('the real Filament edit path updates a known typed setting through SettingsService', function (): void {
    $editor = settingsAdmin(['settings.view', 'settings.update']);
    $taxClass = TaxClass::query()->create([
        'name' => 'Filament tax setting test',
        'slug' => 'filament-tax-setting-test',
        'type' => 'percent',
        'value' => '9.000',
        'is_active' => true,
    ]);
    $setting = Setting::query()->updateOrCreate(
        ['group' => 'tax', 'key' => 'default_tax_class_id'],
        ['type' => 'integer', 'value' => null],
    );

    Livewire::actingAs($editor, 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_number' => (string) $taxClass->id], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBe((string) $taxClass->id)
        ->and($setting->fresh()->typed_value)->toBe($taxClass->id);
});

test('unknown existing settings remain inspectable but cannot be updated', function (): void {
    $editor = settingsAdmin(['settings.view', 'settings.update']);
    $setting = Setting::query()->create([
        'group' => 'legacy',
        'key' => 'legacy_unknown_key',
        'type' => 'string',
        'value' => 'legacy',
    ]);

    $this->actingAs($editor)->get('/admin/settings')->assertOk();
    $this->actingAs($editor)->get('/admin/settings/'.$setting->id.'/edit')->assertForbidden();
});
