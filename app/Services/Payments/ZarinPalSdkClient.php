<?php

namespace App\Services\Payments;

use App\Contracts\Payments\ZarinPalClientInterface;
use ZarinPal\Sdk\Endpoint\PaymentGateway\PaymentGateway;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

class ZarinPalSdkClient implements ZarinPalClientInterface
{
    private PaymentGateway $gateway;

    public function __construct(ZarinPalGatewaySettings $settings)
    {
        $merchantId = strtolower(trim($settings->merchantId));

        if (! PaymentGatewayConfiguration::validMerchantId($merchantId)) {
            throw new \InvalidArgumentException('ZarinPal merchant_id must be a valid UUID.');
        }

        $sdk = new ZarinPal(new Options([
            'merchant_id' => $merchantId,
            'sandbox' => $settings->sandbox,
        ]));

        $this->gateway = $sdk->paymentGateway();
    }

    public function request(
        int $amount,
        string $description,
        string $callbackUrl,
        string $currency,
        ?string $mobile = null,
        ?string $email = null,
    ): array {
        $response = $this->gateway->request(new RequestRequest([
            'amount' => $amount,
            'description' => $description,
            'callback_url' => $callbackUrl,
            'currency' => $currency,
            // SDK v2 serializes both metadata keys and the API rejects null values.
            'mobile' => $mobile ?? '09120000000',
            'email' => $email ?? 'payment@example.com',
        ]));

        return [
            'code' => (int) $response->code,
            'authority' => $response->authority ?? null,
            'message' => $response->message ?? null,
            'fee_type' => $response->fee_type ?? null,
            'fee' => isset($response->fee) ? (int) $response->fee : null,
            'amount' => isset($response->amount) ? (int) $response->amount : null,
        ];
    }

    public function verify(string $authority, int $amount): array
    {
        $response = $this->gateway->verify(new VerifyRequest([
            'authority' => $authority,
            'amount' => $amount,
        ]));

        return [
            'code' => (int) $response->code,
            'ref_id' => $response->ref_id ?? null,
            'card_pan' => $response->card_pan ?? null,
            'card_hash' => $response->card_hash ?? null,
            'fee_type' => $response->fee_type ?? null,
            'fee' => isset($response->fee) ? (int) $response->fee : null,
            'message' => $response->message ?? null,
        ];
    }

    public function redirectUrl(string $authority): string
    {
        return $this->gateway->getRedirectUrl($authority);
    }
}
