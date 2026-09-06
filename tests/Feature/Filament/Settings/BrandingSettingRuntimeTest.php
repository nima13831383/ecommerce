<?php

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\Storefront\StorefrontBranding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function brandingSettingsEditor(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('settings.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('settings.update', 'web'));

    return $user;
}

test('storefront logo falls back safely and uses a configured public asset', function (): void {
    Storage::fake('public');
    $settings = app(SettingsService::class);

    expect(app(StorefrontBranding::class)->logoUrl())->toContain('/storefront/luxira-icon.png');

    Storage::disk('public')->put('branding/logo.png', 'logo');
    $settings->update('branding.logo_path', 'branding/logo.png');

    expect(app(StorefrontBranding::class)->logoUrl())->toContain('/storage/branding/logo.png');
    $this->get('/')->assertOk()->assertSee('/storage/branding/logo.png', false);

    $settings->update('branding.logo_path', '../private/logo.png');
    expect(app(StorefrontBranding::class)->logoUrl())->toContain('/storefront/luxira-icon.png');
});

test('Filament branding setting persists an uploaded logo through the real edit page', function (): void {
    Storage::fake('public');
    $setting = Setting::query()->where('key', 'branding.logo_path')->firstOrFail();

    Livewire::actingAs(brandingSettingsEditor(), 'web')
        ->test(EditSetting::class, ['record' => $setting->getRouteKey()])
        ->fillForm(['value_string' => [UploadedFile::fake()->image('brand.png')]], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    $path = $setting->fresh()->typed_value;

    expect($path)->toStartWith('branding/')->and($path)->toEndWith('.png');
    Storage::disk('public')->assertExists($path);
});
