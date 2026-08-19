<?php

return [
    'plugin_path' => env(
        'POSTAL_SHIPPING_PLUGIN_PATH',
        base_path('codex/plugin/persian-woocommerce-shipping')
    ),

    'tariff_pdf_path' => env(
        'POSTAL_TARIFF_PDF_PATH',
        base_path('codex/نرخنامه1405.pdf')
    ),

    'tapin_service_fee_rials' => (int) env('POSTAL_SHIPPING_TAPIN_SERVICE_FEE_RIALS', 30_000),
    'postal_service_fee_rials' => (int) env('POSTAL_SHIPPING_POSTAL_SERVICE_FEE_RIALS', 35_000),
];
