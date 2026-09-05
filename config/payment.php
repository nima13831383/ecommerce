<?php

return [
    'gateways' => [
        'zarinpal' => [
            'currency' => 'IRR',
        ],
    ],

    // Legacy values are import/diagnostic input only. Runtime configuration is stored
    // in the core Site Settings contract and is never read from this section.
    'legacy' => [
        'default_gateway' => env('STOREFRONT_PAYMENT_GATEWAY'),
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
        ],
    ],
];
