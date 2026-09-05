<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use ZarinPal\Sdk\ZarinPal;

class DiagnoseZarinPal extends Command
{
    protected $signature = 'payment:diagnose-zarinpal';

    protected $description = 'Report safe ZarinPal runtime configuration diagnostics without exposing credentials.';

    public function handle(SettingsService $settings, PaymentGatewayConfiguration $configuration): int
    {
        $merchantId = $settings->get('payment.zarinpal.merchant_id');
        $sandbox = $settings->get('payment.zarinpal.sandbox') === true;
        $callback = route('storefront.payment.callback', ['payment' => 'PAYMENT-NUMBER', 'signature' => 'SIGNATURE']);

        $this->line('Default gateway: '.($settings->get('payment.default_gateway') ?? 'not configured'));
        $this->line('ZarinPal enabled: '.($settings->get('payment.zarinpal.enabled') === true ? 'yes' : 'no'));
        $this->line('Sandbox: '.($sandbox ? 'yes' : 'no'));
        $this->line('Merchant configured: '.($merchantId !== null ? 'yes' : 'no'));
        $this->line('Merchant valid: '.(PaymentGatewayConfiguration::validMerchantId($merchantId) ? 'yes' : 'no'));
        $this->line('Environment: '.app()->environment());
        $this->line('APP_URL: '.config('app.url'));
        $this->line('Callback URL: '.$callback);
        $this->line('SDK available: '.(class_exists(ZarinPal::class) ? 'yes' : 'no'));
        $this->line('Gateway operational: '.($configuration->zarinPal() !== null ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
