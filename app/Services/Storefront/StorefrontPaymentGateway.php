<?php

namespace App\Services\Storefront;

use App\Services\Payments\PaymentGatewayRegistry;
use DomainException;

class StorefrontPaymentGateway
{
    public function __construct(private readonly PaymentGatewayRegistry $gateways) {}

    public function alias(): ?string
    {
        $alias = config('payment.storefront_gateway');
        if (! is_string($alias) || trim($alias) === '') {
            $alias = app()->environment('testing') ? 'fake' : null;
        }

        if ($alias === null || ($alias === 'fake' && ! app()->environment(['local', 'testing']))) {
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
