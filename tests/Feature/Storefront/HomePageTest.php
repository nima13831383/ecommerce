<?php

use Illuminate\Support\Facades\Route;

test('the storefront home page renders the Blade foundation for guests', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<html lang="fa" dir="rtl">', false)
        ->assertSee('class="desktop-header"', false)
        ->assertSee('class="site-footer"', false)
        ->assertSee('id="main-content"', false)
        ->assertSee('storefront/assets/css/generated/tailwind.css', false)
        ->assertSee('storefront/assets/js/homepage/mobile-menu.js', false)
        ->assertSee('storefront/assets/js/core/main.js', false)
        ->assertDontSee('"data":', false);
});

test('the storefront home page references migrated local assets', function (): void {
    $assetPaths = [
        'public/storefront/luxira-icon.png',
        'public/storefront/assets/css/generated/tailwind.css',
        'public/storefront/assets/css/base/fonts.css',
        'public/storefront/assets/css/homepage/header.css',
        'public/storefront/assets/vendor/jquery/jquery.min.js',
        'public/storefront/assets/js/homepage/mobile-menu.js',
    ];

    foreach ($assetPaths as $assetPath) {
        expect(file_exists(base_path($assetPath)))->toBeTrue($assetPath);
    }
});

test('existing API, Breeze, and Filament route registrations remain available', function (): void {
    expect(Route::has('api.v1.products.index'))->toBeTrue()
        ->and(Route::has('api.v1.auth.login'))->toBeTrue()
        ->and(Route::has('login'))->toBeTrue()
        ->and(Route::has('filament.admin.pages.dashboard'))->toBeTrue();
});
