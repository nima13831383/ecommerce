<?php

use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\PostShippingCalculator;

function testPostShippingCalculator(
    int $tapinServiceFeeRials = 30_000,
    int $postalServiceFeeRials = 35_000,
): PostShippingCalculator {
    return new PostShippingCalculator(
        new WordpressShippingDataLoader(dirname(__DIR__, 3).'/codex/plugin/persian-woocommerce-shipping'),
        $tapinServiceFeeRials,
        $postalServiceFeeRials,
    );
}

function shippingQuoteInput(array $overrides = []): ShippingQuoteInput
{
    return new ShippingQuoteInput(...array_replace([
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
    ], $overrides));
}

it('reproduces the supplied Tapin Pishtaz quote exactly', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput());
    $components = collect($result->breakdown)->keyBy('key');

    expect($result->total)->toBe(2_326_500)
        ->and($components['base_postage']['amount'])->toBe(1_700_000)
        ->and($components['package_surcharge']['amount'])->toEqual(320_000)
        ->and($components['postage_tax']['amount'])->toBe(202_000)
        ->and($components['service_total']['amount'])->toBe(71_500)
        ->and($components['tapin_total_adjustment']['amount'])->toBe(33_000);
});

it('uses the current Tapin package adjustment before tax', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'packageSizeId' => 10,
    ]));

    expect($result->metadata['postage_before_tax_rials'])->toBe(2_660_000)
        ->and($result->total)->toBe(3_030_500);
});

it('uses configurable Tapin and postal service fees', function () {
    $result = testPostShippingCalculator(40_000, 50_000)->calculate(shippingQuoteInput());

    expect($result->metadata['service_total_rials'])->toBe(99_000)
        ->and($result->metadata['tapin_total_adjustment_rials'])->toBe(44_000)
        ->and($result->total)->toBe(2_365_000);
});

it('rounds fractional grams to the next one kilogram bracket', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'weightGrams' => 1000.1,
        'packageSizeId' => 1,
    ]));

    expect($result->metadata['weight_whole_grams'])->toBe(1001)
        ->and($result->metadata['weight_bracket_grams'])->toBe(2000)
        ->and($result->metadata['postage_before_tax_rials'])->toBe(1_175_000);
});

it('clamps plugin envelopes and required b envelopes to package size ten', function (int $packageSize) {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'packageSizeId' => $packageSize,
    ]));

    expect($result->metadata['package_size_effective'])->toBe(10)
        ->and($result->warnings)->not->toBeEmpty();
})->with([11, 13, 14, 15]);

it('caps the declared value at one billion rials', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'declaredValueRials' => 2_000_000_000,
    ]));

    expect($result->metadata['declared_value_used_rials'])->toBe(1_000_000_000)
        ->and($result->warnings)->not->toBeEmpty();
});

it('matches the Tapin declared value adjustment at one hundred million rials', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'declaredValueRials' => 100_000_000,
    ]));

    expect($result->metadata['postage_before_tax_rials'])->toBe(2_120_143)
        ->and($result->total)->toBe(2_436_657);
});

it('matches the Tapin fragile surcharge fixture', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'parcelType' => 'fragile_liquid',
    ]));

    expect($result->metadata['postage_before_tax_rials'])->toBe(2_500_000)
        ->and($result->total)->toBe(2_854_500);
});

it('matches the Tapin cash on delivery fixture', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'paymentType' => 'cod',
    ]));

    expect($result->metadata['postage_before_tax_rials'])->toBe(2_170_000)
        ->and($result->total)->toBe(2_491_500);
});

it('returns zero for the plugin storefront postpaid and free modes', function (string $paymentType) {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'paymentType' => $paymentType,
    ]));

    expect($result->total)->toBe(0);
})->with(['postpaid', 'free']);

it('marks Vijeh operational availability as unverified without suppressing its quote', function () {
    $result = testPostShippingCalculator()->calculate(shippingQuoteInput([
        'destinationCityId' => 6931,
        'service' => 'vijeh',
    ]));

    expect($result->available)->toBeNull()
        ->and($result->total)->toBe(3_184_500)
        ->and($result->warnings)->not->toBeEmpty();
});
