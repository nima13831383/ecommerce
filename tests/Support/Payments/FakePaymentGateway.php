<?php

namespace Tests\Support\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Payment;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public bool $initiationSucceeds = true;

    public ?PaymentVerificationResult $verificationResult = null;

    public function alias(): string
    {
        return 'fake';
    }

    public function initiate(Payment $payment): PaymentInitiationResult
    {
        return new PaymentInitiationResult(
            successful: $this->initiationSucceeds,
            providerPaymentIdentifier: $this->initiationSucceeds ? "fake-init-{$payment->id}" : null,
            metadata: ['mode' => 'test'],
            failureReason: $this->initiationSucceeds ? null : 'Fake initiation failure.',
        );
    }

    public function verify(Payment $payment): PaymentVerificationResult
    {
        return $this->verificationResult ?? new PaymentVerificationResult(
            verified: true,
            providerReference: "fake-reference-{$payment->id}",
            amount: $payment->amount,
            currency: $payment->currency,
            metadata: ['mode' => 'test'],
        );
    }
}
