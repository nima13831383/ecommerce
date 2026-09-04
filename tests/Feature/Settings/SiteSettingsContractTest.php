<?php

use App\Exceptions\UnknownSettingException;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use App\Settings\SettingRegistry;
use Illuminate\Validation\ValidationException;

test('all registered core settings are persisted with their declared types and defaults', function (): void {
    $definitions = SettingRegistry::coreDefinitions();

    expect($definitions)->not->toBeEmpty();

    foreach ($definitions as $definition) {
        $row = Setting::query()
            ->where('group', $definition->group)
            ->where('key', $definition->key)
            ->first();

        expect($row)->not->toBeNull()
            ->and($row->type)->toBe($definition->type)
            ->and(Setting::query()->where('group', $definition->group)->where('key', $definition->key)->count())->toBe(1);
    }
});

test('settings sync adds only missing rows and preserves existing values', function (): void {
    $service = app(SettingsService::class);
    $service->update('shipping.mode', 'free');
    Setting::query()->where('group', 'shipping')->where('key', 'shipping.packages')->delete();

    $dryRun = $service->sync(dryRun: true);

    expect($dryRun['added'])->toContain('shipping.packages')
        ->and(Setting::query()->where('key', 'shipping.packages')->exists())->toBeFalse();

    $result = $service->sync();

    expect($result['added'])->toContain('shipping.packages')
        ->and($service->get('shipping.mode'))->toBe('free')
        ->and($service->get('shipping.packages'))->toBe([]);

    expect($service->sync()['added'])->toBe([]);
});

test('persisted null values remain null and typed money values remain integers', function (): void {
    $service = app(SettingsService::class);

    expect($service->get('shipping.origin_province_id'))->toBeNull()
        ->and(Setting::query()->where('key', 'shipping.origin_province_id')->firstOrFail()->typed_value)->toBeNull()
        ->and($service->get('shipping.fixed_rate_amount'))->toBeInt();
});

test('status reports missing, unknown, and incomplete configuration without exposing values', function (): void {
    $service = app(SettingsService::class);
    Setting::query()->where('group', 'shipping')->where('key', 'shipping.packages')->delete();
    Setting::query()->create(['group' => 'legacy', 'key' => 'old_private_key', 'type' => 'string', 'value' => 'secret']);

    $status = $service->status();

    expect($status['registered'])->toBe(count(SettingRegistry::coreDefinitions()))
        ->and($status['missing'])->toContain('shipping.packages')
        ->and($status['unknown'])->toContain(['group' => 'legacy', 'key' => 'old_private_key'])
        ->and($status['needs_configuration'])->toContain('shipping.origin_province_id');
});

test('unknown setting updates fail through the service authority', function (): void {
    expect(fn () => app(SettingsService::class)->update('not.registered', 'value'))
        ->toThrow(UnknownSettingException::class);
});

test('switching to calculator rejects an incomplete shipping configuration', function (): void {
    $service = app(SettingsService::class);
    $service->update('shipping.mode', 'free');

    expect(fn () => $service->update('shipping.mode', 'calculator'))
        ->toThrow(ValidationException::class);

    expect($service->get('shipping.mode'))->toBe('free');
});

test('settings resource exposes value editing only and cannot create or delete rows', function (): void {
    expect(SettingResource::canCreate())->toBeFalse()
        ->and(SettingResource::canDelete())->toBeFalse()
        ->and(SettingResource::getPages())->toHaveKeys(['index', 'edit'])
        ->and(SettingResource::getPages())->not->toHaveKey('create');
});
