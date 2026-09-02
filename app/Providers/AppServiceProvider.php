<?php

namespace App\Providers;

use App\Events\CustomerLifecycle\OrderCancelled;
use App\Events\CustomerLifecycle\OrderPlaced;
use App\Events\CustomerLifecycle\PaymentSucceeded;
use App\Events\CustomerLifecycle\ShipmentDelivered;
use App\Events\CustomerLifecycle\ShipmentReady;
use App\Events\CustomerLifecycle\ShipmentShipped;
use App\Listeners\Notifications\CreateCustomerLifecycleNotification;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Event::listen([
            OrderPlaced::class,
            PaymentSucceeded::class,
            OrderCancelled::class,
            ShipmentReady::class,
            ShipmentShipped::class,
            ShipmentDelivered::class,
        ], CreateCustomerLifecycleNotification::class);

        Gate::before(function (User $user): ?bool {
            if ($user->trashed()) {
                return false;
            }

            return $user->hasRole('super-admin') ? true : null;
        });

    }
}
