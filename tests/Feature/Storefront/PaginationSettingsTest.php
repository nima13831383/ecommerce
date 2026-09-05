<?php

use App\Models\Product;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use App\Settings\SettingRegistry;
use App\Support\PersianNumber;
use Illuminate\Validation\ValidationException;

test('catalog and blog archive settings are registered as persisted typed defaults', function (): void {
    expect(SettingRegistry::get('catalog.products_per_page')->default)->toBe(10)
        ->and(SettingRegistry::get('blog.posts_per_page')->default)->toBe(10)
        ->and(SettingRegistry::get('catalog.products_per_page')->group)->toBe('catalog')
        ->and(SettingRegistry::get('blog.posts_per_page')->group)->toBe('blog')
        ->and(app(SettingsService::class)->get('catalog.products_per_page'))->toBe(10)
        ->and(app(SettingsService::class)->get('blog.posts_per_page'))->toBe(10);

    expect(Setting::query()->where('key', 'catalog.products_per_page')->value('value'))->toBe('10')
        ->and(Setting::query()->where('key', 'blog.posts_per_page')->value('value'))->toBe('10');
});

test('archive pagination settings validate and preserve existing values during core synchronization', function (): void {
    $settings = app(SettingsService::class);
    $settings->update('catalog.products_per_page', 6);
    $settings->update('blog.posts_per_page', 7);

    expect($settings->get('catalog.products_per_page'))->toBe(6)
        ->and($settings->get('blog.posts_per_page'))->toBe(7);

    $settings->sync();

    expect($settings->get('catalog.products_per_page'))->toBe(6)
        ->and($settings->get('blog.posts_per_page'))->toBe(7)
        ->and(fn () => $settings->update('catalog.products_per_page', 0))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update('blog.posts_per_page', 101))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update('blog.posts_per_page', '5.5'))->toThrow(ValidationException::class);
});

test('product archive uses the configured setting and retains ASCII URL pagination with Persian visible digits', function (): void {
    app(SettingsService::class)->update('catalog.products_per_page', 2);
    foreach (range(1, 3) as $index) {
        Product::query()->create([
            'name' => "Pagination Product {$index}",
            'slug' => "pagination-product-{$index}",
            'type' => 'simple',
            'price' => 10_000,
            'status' => 'published',
            'published_at' => now(),
            'stock_quantity' => 10,
            'stock_status' => 'in_stock',
        ]);
    }

    $this->get('/products?sort=newest')
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('۲', false);
});

test('persian number presentation does not alter canonical values', function (): void {
    expect(PersianNumber::digits('1234567890'))->toBe('۱۲۳۴۵۶۷۸۹۰')
        ->and(PersianNumber::money(19_800_000))->toBe('۱۹,۸۰۰,۰۰۰ ریال')
        ->and(PersianNumber::percentage(20))->toBe('۲۰٪');
});
