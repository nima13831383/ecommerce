<?php

namespace App\Providers;

use App\Contracts\Sms\OtpSenderInterface;
use App\Events\CustomerLifecycle\OrderCancelled;
use App\Events\CustomerLifecycle\OrderPlaced;
use App\Events\CustomerLifecycle\PaymentSucceeded;
use App\Events\CustomerLifecycle\ShipmentDelivered;
use App\Events\CustomerLifecycle\ShipmentReady;
use App\Events\CustomerLifecycle\ShipmentShipped;
use App\Listeners\Notifications\CreateCustomerLifecycleNotification;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\User;
use App\Observers\PostStorefrontCacheObserver;
use App\Observers\ProductImageStorefrontCacheObserver;
use App\Observers\ProductStorefrontCacheObserver;
use App\Observers\ProductVariationStorefrontCacheObserver;
use App\Services\Payments\PaymentCallbackSigner;
use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\ZarinPalPaymentGateway;
use App\Services\Payments\ZarinPalSdkClient;
use App\Services\Sms\SmsIrOtpSender;
use App\Services\Storefront\StorefrontBranding;
use App\Services\Storefront\StorefrontCartContext;
use App\Support\DatabaseSafetyGuard;
use Illuminate\Console\Events\CommandStarting;
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
        $this->app->bind(OtpSenderInterface::class, SmsIrOtpSender::class);
        $this->app->bind(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $gateways = [];

            $zarinPal = app(PaymentGatewayConfiguration::class)->zarinPal();
            if ($zarinPal !== null) {
                $gateways[] = new ZarinPalPaymentGateway(new ZarinPalSdkClient($zarinPal), new PaymentCallbackSigner);
            }

            return new PaymentGatewayRegistry($gateways);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            DatabaseSafetyGuard::assertNoDestructiveArtisanCommand((string) $event->command);
        });

        Product::observe(ProductStorefrontCacheObserver::class);
        Post::observe(PostStorefrontCacheObserver::class);
        ProductImage::observe(ProductImageStorefrontCacheObserver::class);
        ProductVariation::observe(ProductVariationStorefrontCacheObserver::class);

        View::composer('storefront.*', function ($view): void {
            $context = app(StorefrontCartContext::class);
            $view->with([
                'storefrontCart' => $context->present($context->current()),
                'storefrontLogo' => app(StorefrontBranding::class)->logoUrl(),
            ]);
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
