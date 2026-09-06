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

    foreach (['hero/desktop/1.png', 'hero/desktop/2.png', 'hero/desktop/3.png', 'hero/mobile/1.png', 'hero/mobile/2.png', 'hero/mobile/3.png', 'promos/accessories.png', 'promos/skincare.png'] as $assetPath) {
        expect(file_exists(base_path('public/storefront/assets/homepage/'.$assetPath)))->toBeTrue($assetPath);
    }
});

test('the storefront home page uses paired hero artwork and configured branding with a safe fallback', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('assets/homepage/hero/desktop/1.png', false)
        ->assertSee('assets/homepage/hero/mobile/1.png', false)
        ->assertSee('assets/homepage/promos/accessories.png', false)
        ->assertSee('assets/homepage/promos/skincare.png', false)
        ->assertSee('storefront/luxira-icon.png', false)
        ->assertSee('data-component="hero-slider"', false);
});

test('shared header removes category and brand navigation while newsletter keeps the shared design', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertDontSee('href="#categories"', false)
        ->assertDontSee('href="#brands"', false)
        ->assertSee('در خبرنامه لوکسیرا عضو شوید')
        ->assertSee('assets/css/components/newsletter-shared.css', false)
        ->assertSee('not-found-newsletter', false);
});

test('existing API, Breeze, and Filament route registrations remain available', function (): void {
    expect(Route::has('api.v1.products.index'))->toBeTrue()
        ->and(Route::has('api.v1.auth.login'))->toBeTrue()
        ->and(Route::has('login'))->toBeTrue()
        ->and(Route::has('filament.admin.pages.dashboard'))->toBeTrue();
});
