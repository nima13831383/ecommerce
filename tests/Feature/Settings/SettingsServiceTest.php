<?php

use App\Exceptions\UnknownSettingException;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Services\Settings\SettingsService;
use App\Settings\SettingRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

test('known settings return typed values and centralized defaults', function (): void {
    expect(app(SettingsService::class)->get('default_tax_class_id'))->toBeNull()
        ->and(SettingRegistry::get('default_tax_class_id')->type)->toBe('integer');

    $taxClass = TaxClass::query()->create([
        'name' => 'Tax setting test',
        'slug' => 'tax-setting-test',
        'type' => 'percent',
        'value' => '9.000',
        'is_active' => true,
    ]);

    app(SettingsService::class)->update('default_tax_class_id', $taxClass->id);

    expect(app(SettingsService::class)->get('default_tax_class_id'))->toBe($taxClass->id)
        ->and(Setting::query()->where('key', 'default_tax_class_id')->value('value'))->toBe((string) $taxClass->id);
});

test('unknown setting keys cannot be read or written', function (): void {
    $service = app(SettingsService::class);

    expect(fn () => $service->get('order_timeout_minutes'))->toThrow(UnknownSettingException::class)
        ->and(fn () => $service->update('order_timeout_minutes', 30))->toThrow(UnknownSettingException::class);
});

test('referential settings reject missing and inactive tax classes without persistence', function (): void {
    $service = app(SettingsService::class);

    expect(fn () => $service->update('default_tax_class_id', 999999))->toThrow(ValidationException::class);

    $inactive = TaxClass::query()->create([
        'name' => 'Inactive tax setting test',
        'slug' => 'inactive-tax-setting-test',
        'type' => 'percent',
        'value' => '9.000',
        'is_active' => false,
    ]);

    expect(fn () => $service->update('default_tax_class_id', $inactive->id))->toThrow(ValidationException::class)
        ->and(Setting::query()->where('key', 'default_tax_class_id')->exists())->toBeFalse();
});

test('settings updates log safe context without values', function (): void {
    Log::spy();
    $taxClass = TaxClass::query()->create([
        'name' => 'Audited tax setting test',
        'slug' => 'audited-tax-setting-test',
        'type' => 'percent',
        'value' => '9.000',
        'is_active' => true,
    ]);

    app(SettingsService::class)->update('default_tax_class_id', $taxClass->id);

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'settings.updated'
            && ($context['setting_key'] ?? null) === 'default_tax_class_id'
            && ! array_key_exists('old_value', $context)
            && ! array_key_exists('new_value', $context);
    });
});
