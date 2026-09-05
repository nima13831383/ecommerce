<?php

namespace App\Services\Storefront;

use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Payments\PaymentGatewayRegistry;
use DomainException;

class StorefrontPaymentGateway
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly PaymentGatewayConfiguration $configuration,
    ) {}

    public function alias(): ?string
    {
        if (app()->environment('testing') && $this->gateways->has('fake')) {
            return 'fake';
        }

        $alias = $this->configuration->defaultGateway();
        if ($alias === null) {
            return null;
        }

        try {
            $this->gateways->gateway($alias);
        } catch (DomainException) {
            return null;
        }

        return $alias;
    }
}
