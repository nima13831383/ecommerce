<?php

use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\PostShippingCalculator;

it('matches captured Tapin public calculator totals', function (array $input, int $expectedTotal) {
    $calculator = new PostShippingCalculator(new WordpressShippingDataLoader(
        dirname(__DIR__, 3).'/codex/plugin/persian-woocommerce-shipping',
    ));

    expect($calculator->calculate(new ShippingQuoteInput(...$input))->total)->toBe($expectedTotal);
})->with([
    'Pishtaz supplied Gilan to Ilam size 6 quote' => [[
        'originProvinceId' => 2,
        'originCityId' => 4391,
        'destinationProvinceId' => 27,
        'destinationCityId' => 6971,
        'weightGrams' => 5_000,
        'declaredValueRials' => 50_000_000,
        'parcelType' => 'normal',
        'paymentType' => 'online',
        'packageSizeId' => 6,
        'service' => 'pishtaz',
    ], 2_326_500],
    'Pishtaz other province size 1 quote' => [[
        'originProvinceId' => 2,
        'originCityId' => 4391,
        'destinationProvinceId' => 27,
        'destinationCityId' => 6971,
        'weightGrams' => 5_000,
        'declaredValueRials' => 50_000_000,
        'parcelType' => 'normal',
        'paymentType' => 'online',
        'packageSizeId' => 1,
        'service' => 'pishtaz',
    ], 1_974_500],
    'Pishtaz same province quote' => [[
        'originProvinceId' => 2,
        'originCityId' => 4391,
        'destinationProvinceId' => 2,
        'destinationCityId' => 441,
        'weightGrams' => 5_000,
        'declaredValueRials' => 50_000_000,
        'parcelType' => 'normal',
        'paymentType' => 'online',
        'packageSizeId' => 1,
        'service' => 'pishtaz',
    ], 1_534_500],
    'Vijeh other province size 6 quote' => [[
        'originProvinceId' => 2,
        'originCityId' => 41,
        'destinationProvinceId' => 27,
        'destinationCityId' => 6931,
        'weightGrams' => 5_000,
        'declaredValueRials' => 50_000_000,
        'parcelType' => 'normal',
        'paymentType' => 'online',
        'packageSizeId' => 6,
        'service' => 'vijeh',
    ], 3_184_500],
]);
