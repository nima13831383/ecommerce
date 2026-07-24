<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\{Order, Shipment};
use App\Observers\{OrderObserver, ShipmentObserver};



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Shipment::observe(ShipmentObserver::class);
        Order::observe(OrderObserver::class);
    }
}
