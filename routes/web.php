<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\StaticPageController;
use App\Http\Controllers\TapinCalculatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('storefront.home');
Route::get('/products', [ProductController::class, 'index'])->name('storefront.products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('storefront.products.show');
Route::get('/blog', [BlogController::class, 'index'])->name('storefront.blog.index');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('storefront.blog.show');
Route::get('/about', [StaticPageController::class, 'about'])->name('storefront.about');
Route::get('/contact', [StaticPageController::class, 'contact'])->name('storefront.contact');
Route::get('/faq', [StaticPageController::class, 'faq'])->name('storefront.faq');
Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('storefront.cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('storefront.cart.coupon.remove');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('storefront.cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('storefront.cart.items.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('storefront.cart.clear');

Route::redirect('/dashboard', '/account')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('storefront.checkout.success');
    Route::post('/orders/{order}/payment', [PaymentController::class, 'initiate'])->name('storefront.payment.initiate');
    Route::get('/payment/return/{payment}', [PaymentController::class, 'paymentReturn'])->name('storefront.payment.return');
    Route::get('/payment/result/{payment}', [PaymentController::class, 'result'])->name('storefront.payment.result');
    Route::get('/account/orders', [OrderController::class, 'index'])->name('storefront.account.orders');
    Route::get('/account/orders/{order}', [OrderController::class, 'show'])->name('storefront.account.orders.show');
    Route::post('/cart/shipping/quote', [CartController::class, 'quoteShipping'])->name('storefront.cart.shipping.quote');
    Route::get('/account', [AccountController::class, 'index'])->name('storefront.account');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('storefront.account.profile');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('storefront.account.profile.update');
    Route::get('/account/addresses', [AddressController::class, 'index'])->name('storefront.account.addresses');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('storefront.account.addresses.store');
    Route::patch('/account/addresses/{address}', [AddressController::class, 'update'])->name('storefront.account.addresses.update');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('storefront.account.addresses.destroy');
    Route::get('/locations/provinces/{province}/cities', [AddressController::class, 'cities'])->name('storefront.locations.cities');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/payment/callback/{payment}', [PaymentController::class, 'providerCallback'])
    ->name('storefront.payment.callback');

if (app()->environment(['local', 'testing'])) {
    Route::get('/shipping-calculator-test', [TapinCalculatorController::class, 'show'])
        ->name('shipping-calculator-test.show');
    Route::post('/shipping-calculator-test', [TapinCalculatorController::class, 'calculate'])
        ->name('shipping-calculator-test.calculate');
}

require __DIR__.'/auth.php';
