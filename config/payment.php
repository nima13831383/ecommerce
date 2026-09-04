<?php

return [
    'storefront_gateway' => env('STOREFRONT_PAYMENT_GATEWAY'),

    'gateways' => [
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
            'currency' => 'IRR',
        ],
    ],
];
