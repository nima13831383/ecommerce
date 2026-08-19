<?php

use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\ShippingOptionCatalog;

beforeEach(function () {
    $this->loader = new WordpressShippingDataLoader(
        dirname(__DIR__, 3).'/codex/plugin/persian-woocommerce-shipping'
    );
});

it('loads provinces and cities from the plugin json data', function () {
    expect($this->loader->provinces())
        ->toHaveCount(31)
        ->and($this->loader->provinces()[1])->toBe('تهران')
        ->and($this->loader->cities(1)[1])->toBe('تهران')
        ->and($this->loader->cityBelongsToProvince(31, 31))->toBeTrue()
        ->and($this->loader->cityBelongsToProvince(31, 1))->toBeFalse();
});

it('parses plugin mappings without bootstrapping wordpress', function () {
    $catalog = new ShippingOptionCatalog($this->loader);

    expect($this->loader->areNeighboringProvinces(1, 31))->toBeTrue()
        ->and($this->loader->areNeighboringProvinces(1, 2))->toBeFalse()
        ->and($catalog->packageSizes()[11])->toBe('پاکت جوف A5')
        ->and($catalog->packageSizes()[14])->toBe('پاکت جوف B5');
});

it('loads the inspected pure php rate arrays directly', function () {
    expect($this->loader->rateTable('pishtaz', 1)[1000][1]['in'])->toBe(700000)
        ->and($this->loader->rateTable('vijeh', 1)[1000][1]['out'])->toBe(1450000)
        ->and($this->loader->rateTableRelativePath('pishtaz', 5))
        ->toBe('data/rates/tapin-pishtaz-border.php');
});
