<?php

use App\Services\Storefront\StorefrontCacheRebuildService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:expire-reservations')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(fn () => app(StorefrontCacheRebuildService::class)->recoverQueuedRuns())
    ->name('recover-storefront-cache-rebuilds')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(fn () => app(StorefrontCacheRebuildService::class)->requestPrune())
    ->name('prune-storefront-cache-generations')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('queue.default') === 'redis')
    ->withoutOverlapping()
    ->onOneServer();
