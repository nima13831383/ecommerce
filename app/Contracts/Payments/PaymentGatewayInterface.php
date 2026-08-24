<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

interface PaymentGatewayInterface
{
    public function alias(): string;

    public function initiate(Payment $payment): PaymentInitiationResult;

    public function verify(Payment $payment): PaymentVerificationResult;
}
