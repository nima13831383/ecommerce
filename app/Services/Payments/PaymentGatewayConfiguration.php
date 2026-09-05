<?php

namespace App\Services\Payments;

use App\Services\Settings\SettingsService;

class PaymentGatewayConfiguration
{
    public function __construct(private readonly SettingsService $settings) {}

    public function defaultGateway(): ?string
    {
        $gateway = $this->settings->get('payment.default_gateway');

        return is_string($gateway) && $gateway === 'zarinpal' ? $gateway : null;
    }

    public function zarinPal(): ?ZarinPalGatewaySettings
    {
        if ($this->defaultGateway() !== 'zarinpal') {
            return null;
        }

        if ($this->settings->get('payment.zarinpal.enabled') !== true) {
            return null;
        }

        $merchantId = $this->settings->get('payment.zarinpal.merchant_id');
        if (! self::validMerchantId($merchantId)) {
            return null;
        }

        $sandbox = $this->settings->get('payment.zarinpal.sandbox') === true;
        if ($sandbox && app()->isProduction()) {
            return null;
        }

        return new ZarinPalGatewaySettings(
            merchantId: strtolower(trim($merchantId)),
            sandbox: $sandbox,
        );
    }

    public static function validMerchantId(mixed $merchantId): bool
    {
        if (! is_string($merchantId)) {
            return false;
        }

        return (bool) preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            strtolower(trim($merchantId)),
        );
    }
}
