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
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentCallbackSigner;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\ZarinPalPaymentGateway;
use App\Services\Payments\ZarinPalSdkClient;
use App\Services\Storefront\StorefrontCartContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $gateways = [];

            if (app()->environment(['local', 'testing'])) {
                $gateways[] = new FakePaymentGateway;
            }

            if ($this->validZarinPalConfiguration()) {
                $gateways[] = new ZarinPalPaymentGateway(new ZarinPalSdkClient, new PaymentCallbackSigner);
            }

            return new PaymentGatewayRegistry($gateways);
        });
    }

    private function validZarinPalConfiguration(): bool
    {
        $merchantId = strtolower(trim((string) config('payment.gateways.zarinpal.merchant_id')));

        if (app()->isProduction() && (bool) config('payment.gateways.zarinpal.sandbox', false)) {
            return false;
        }

        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $merchantId);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('storefront.*', function ($view): void {
            $context = app(StorefrontCartContext::class);
            $view->with('storefrontCart', $context->present($context->current()));
        });

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
