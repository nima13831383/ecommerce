<?php

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use App\Services\Storefront\StorefrontCacheStoreResolver;
use App\Settings\SettingRegistry;
use Illuminate\Validation\ValidationException;

test('storefront cache settings are registered, persisted, and default to the database backend', function (): void {
    expect(SettingRegistry::get('cache.store')->default)->toBe('database')
        ->and(SettingRegistry::get('cache.products.ttl_seconds')->default)->toBe(3600)
        ->and(SettingRegistry::get('cache.blog.ttl_seconds')->default)->toBe(3600)
        ->and(SettingRegistry::get('cache.lock_seconds')->default)->toBe(60)
        ->and(SettingRegistry::get('cache.stale_seconds')->default)->toBe(86400)
        ->and(SettingRegistry::get('cache.refresh_ahead.enabled')->default)->toBeTrue()
        ->and(SettingRegistry::get('cache.refresh_ahead_percent')->default)->toBe(15)
        ->and(Setting::query()->where('group', 'cache')->count())->toBe(7)
        ->and(app(StorefrontCacheStoreResolver::class)->name())->toBe('database');
});

test('cache setting synchronization preserves an existing operator value', function (): void {
    $setting = Setting::query()->where('key', 'cache.products.ttl_seconds')->firstOrFail();
    $setting->update(['value' => '7200']);

    app(SettingsService::class)->sync();

    expect($setting->fresh()->value)->toBe('7200');
});

test('redis cannot be selected when its configured infrastructure is unavailable', function (): void {
    expect(fn () => app(SettingsService::class)->update('cache.store', 'redis'))
        ->toThrow(ValidationException::class)
        ->and(app(StorefrontCacheStoreResolver::class)->name())->toBe('database');
});
