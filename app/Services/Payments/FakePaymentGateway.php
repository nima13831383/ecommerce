<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Payment;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

/**
 * Local/testing-only gateway used to exercise the provider boundary without a real provider.
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    public function alias(): string
    {
        return 'fake';
    }

    public function initiate(Payment $payment): PaymentInitiationResult
    {
        return new PaymentInitiationResult(
            successful: true,
            providerPaymentIdentifier: "fake-init-{$payment->id}",
            metadata: ['mode' => 'local'],
        );
    }

    public function verify(Payment $payment): PaymentVerificationResult
    {
        return new PaymentVerificationResult(
            verified: true,
            providerReference: "fake-reference-{$payment->id}",
            amount: $payment->amount,
            currency: $payment->currency,
            metadata: ['mode' => 'local'],
        );
    }
}
