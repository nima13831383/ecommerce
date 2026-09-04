<?php

use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
    Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('api.v1.products.show');
    Route::post('products/{product}/resolve-variation', [ProductController::class, 'resolveVariation'])->name('api.v1.products.resolve-variation');

    // Breeze's web session is the single first-party customer auth boundary.
    Route::middleware('web')->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::post('auth/register', [CustomerAuthController::class, 'register'])->name('api.v1.auth.register');
            Route::post('auth/login', [CustomerAuthController::class, 'login'])->name('api.v1.auth.login');
        });

        Route::middleware('auth:web')->group(function (): void {
            Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('auth/me', [CustomerAuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });
});
