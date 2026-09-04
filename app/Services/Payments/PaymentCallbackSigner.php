<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

class PaymentCallbackSigner
{
    public function sign(string $paymentNumber): string
    {
        return hash_hmac('sha256', $paymentNumber, (string) config('app.key'));
    }

    public function valid(string $paymentNumber, ?string $signature): bool
    {
        return is_string($signature)
            && $signature !== ''
            && Str::length($signature) === 64
            && hash_equals($this->sign($paymentNumber), $signature);
    }
}
