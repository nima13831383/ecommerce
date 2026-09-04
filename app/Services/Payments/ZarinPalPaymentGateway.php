<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Contracts\Payments\ZarinPalClientInterface;
use App\Models\Payment;
use App\Services\Payments\Data\PaymentInitiationResult;
use App\Services\Payments\Data\PaymentVerificationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZarinPalPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ZarinPalClientInterface $client,
        private readonly PaymentCallbackSigner $callbackSigner,
    ) {}

    public function alias(): string
    {
        return 'zarinpal';
    }

    public function initiate(Payment $payment): PaymentInitiationResult
    {
        $payment->loadMissing('order.user');
        $config = config('payment.gateways.zarinpal', []);
        $currency = (string) ($config['currency'] ?? 'IRR');
        $callbackUrl = route('storefront.payment.callback', [
            'payment' => $payment->payment_number,
            'signature' => $this->callbackSigner->sign((string) $payment->payment_number),
        ]);
        $orderNumber = $payment->order?->order_number ?? $payment->order_id;

        try {
            $response = $this->client->request(
                (int) $payment->amount,
                'پرداخت سفارش '.$orderNumber,
                $callbackUrl,
                $currency,
                $payment->order?->customer_mobile,
                $payment->order?->customer_email ?? $payment->order?->user?->email,
            );

            $code = (int) ($response['code'] ?? 0);
            $authority = $response['authority'] ?? null;
            if ($code !== 100 || ! is_string($authority) || $authority === '') {
                return new PaymentInitiationResult(
                    successful: false,
                    metadata: ['provider' => 'zarinpal', 'code' => $code],
                    failureReason: 'ZarinPal payment request was rejected.',
                );
            }

            $redirectUrl = $this->client->redirectUrl($authority);

            return new PaymentInitiationResult(
                successful: true,
                providerPaymentIdentifier: $authority,
                redirectUrl: $redirectUrl,
                metadata: [
                    'provider' => 'zarinpal',
                    'code' => $code,
                    'fee_type' => $response['fee_type'] ?? null,
                    'fee' => isset($response['fee']) ? (int) $response['fee'] : null,
                    'redirect_url' => $redirectUrl,
                    'currency' => $currency,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('ZarinPal payment request failed.', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception' => $exception::class,
            ]);

            return new PaymentInitiationResult(
                successful: false,
                metadata: ['provider' => 'zarinpal'],
                failureReason: 'ZarinPal payment is temporarily unavailable.',
            );
        }
    }

    public function verify(Payment $payment): PaymentVerificationResult
    {
        $authority = (string) $payment->authority;
        $currency = (string) (config('payment.gateways.zarinpal.currency') ?? 'IRR');

        try {
            $response = $this->client->verify($authority, (int) $payment->amount);
            $code = (int) ($response['code'] ?? 0);
            $verified = in_array($code, [100, 101], true);

            return new PaymentVerificationResult(
                verified: $verified,
                providerReference: isset($response['ref_id']) ? (string) $response['ref_id'] : null,
                amount: $verified ? (int) $payment->amount : null,
                currency: $verified ? $currency : null,
                metadata: [
                    'provider' => 'zarinpal',
                    'code' => $code,
                    'fee_type' => $response['fee_type'] ?? null,
                    'fee' => isset($response['fee']) ? (int) $response['fee'] : null,
                ],
                failureReason: $verified ? null : 'ZarinPal payment could not be verified.',
            );
        } catch (Throwable $exception) {
            Log::warning('ZarinPal payment verification failed.', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception' => $exception::class,
            ]);

            return new PaymentVerificationResult(
                verified: false,
                metadata: ['provider' => 'zarinpal'],
                failureReason: 'ZarinPal payment could not be verified.',
            );
        }
    }
}
