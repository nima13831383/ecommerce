<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use DomainException;

class PaymentGatewayRegistry
{
    /** @param array<int, PaymentGatewayInterface> $gateways */
    public function __construct(private array $gateways = []) {}

    public function gateway(string $alias): PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->alias() === $alias) {
                return $gateway;
            }
        }

        throw new DomainException("The payment gateway [{$alias}] is not available.");
    }

    public function has(string $alias): bool
    {
        try {
            $this->gateway($alias);
        } catch (DomainException) {
            return false;
        }

        return true;
    }
}
