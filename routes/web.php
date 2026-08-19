<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TapinCalculatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/shipping-calculator-test', [TapinCalculatorController::class, 'show'])
    ->name('shipping-calculator-test.show');
Route::post('/shipping-calculator-test', [TapinCalculatorController::class, 'calculate'])
    ->name('shipping-calculator-test.calculate');

require __DIR__.'/auth.php';
