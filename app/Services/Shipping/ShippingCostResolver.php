<?php

namespace App\Services\Shipping;

use App\Enums\ShippingMode;
use App\Exceptions\ShippingConfigurationException;
use App\Models\Cart;
use App\Models\ProductVariation;
use App\Services\Settings\SettingsService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\DTO\ShippingQuoteResult;

class ShippingCostResolver
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WordpressShippingDataLoader $geography,
        private readonly ShippingOptionCatalog $options,
        private readonly PostShippingCalculator $calculator,
    ) {}

    public function quote(Cart $cart, int $destinationProvinceId, int $destinationCityId, string $service, string $paymentType): ShippingQuoteResult
    {
        $mode = ShippingMode::tryFrom((string) $this->settings->get('shipping.mode', 'calculator'));
        if ($mode === null) {
            throw new ShippingConfigurationException('روش محاسبه هزینه ارسال نامعتبر است.');
        }

        $destination = $this->geography->cityName($destinationCityId, $destinationProvinceId);
        if ($this->geography->provinceName($destinationProvinceId) === null || $destination === null) {
            throw new ShippingConfigurationException('مقصد ارسال نامعتبر است.');
        }

        if ($mode === ShippingMode::Free) {
            return $this->zero($service, 'free', 'ارسال رایگان', []);
        }

        if ($mode === ShippingMode::Fixed) {
            $amount = (int) $this->settings->get('shipping.fixed_rate_amount', 0);

            return new ShippingQuoteResult($service, true, $amount, 'IRR', [['key' => 'fixed_rate', 'label' => 'نرخ ثابت', 'amount' => $amount]], metadata: ['calculation_mode' => 'fixed']);
        }

        $metrics = $this->metrics($cart);
        [$originProvinceId, $originCityId] = $this->origin();
        $package = $this->selectPackage($metrics['volume'], $metrics['weight_grams']);
        if (! in_array($service, array_keys($this->options->services()), true)) {
            throw new ShippingConfigurationException('سرویس پستی پشتیبانی نمی‌شود.');
        }

        $quote = $this->calculator->calculate(new ShippingQuoteInput(
            originProvinceId: $originProvinceId,
            originCityId: $originCityId,
            destinationProvinceId: $destinationProvinceId,
            destinationCityId: $destinationCityId,
            weightGrams: $metrics['weight_grams'],
            declaredValueRials: (int) $cart->subtotal,
            parcelType: $metrics['parcel_type'],
            paymentType: $paymentType,
            packageSizeId: $package['code'],
            service: $service,
        ));

        return new ShippingQuoteResult($quote->service, $quote->available, $quote->total, $quote->currency, $quote->breakdown, $quote->warnings, [...$quote->metadata, 'calculation_mode' => 'calculator', ...$metrics, 'package' => $package, 'origin_province_id' => $originProvinceId, 'origin_city_id' => $originCityId, 'origin_province_name' => $this->geography->provinceName($originProvinceId), 'origin_city_name' => $this->geography->cityName($originCityId, $originProvinceId), 'destination_province_id' => $destinationProvinceId, 'destination_city_id' => $destinationCityId, 'destination_province_name' => $this->geography->provinceName($destinationProvinceId), 'destination_city_name' => $destination]);
    }

    /** Metrics use kilograms for stored weight and cubic centimetres for stored volume. */
    /** @return array{weight_grams: int, volume: float, parcel_type: string} */
    public function metrics(Cart $cart): array
    {
        $weight = 0.0;
        $volume = 0.0;
        $fragile = false;
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                throw new ShippingConfigurationException('محصول سبد قابل ارسال نیست.');
            }

            $variation = $item->variation;
            $weightKg = $variation instanceof ProductVariation && $variation->weight !== null ? (float) $variation->weight : (float) ($product->weight ?? 0);
            $itemVolume = $variation instanceof ProductVariation && $variation->volume !== null ? (float) $variation->volume : (float) ($product->volume ?? 0);
            if ($weightKg <= 0 || $itemVolume <= 0) {
                throw new ShippingConfigurationException('وزن و حجم همه محصولات قابل ارسال باید مثبت باشد.');
            }

            $weight += $weightKg * 1000 * (int) $item->quantity;
            $volume += $itemVolume * (int) $item->quantity;
            $fragile = $fragile || $product->parcel_type === 'fragile';
        }

        return ['weight_grams' => (int) ceil($weight), 'volume' => $volume, 'parcel_type' => $fragile ? 'fragile_liquid' : 'normal'];
    }

    /** @return array{0: int, 1: int} */
    private function origin(): array
    {
        $province = (int) $this->settings->get('shipping.origin_province_id', 0);
        $city = (int) $this->settings->get('shipping.origin_city_id', 0);
        if ($this->geography->provinceName($province) === null || ! $this->geography->cityBelongsToProvince($city, $province)) {
            throw new ShippingConfigurationException('استان و شهر مبدأ ارسال معتبر نیستند.');
        }

        return [$province, $city];
    }

    /** @return array{code: int, id: string, name: string, capacity_volume: float, max_weight: float} */
    private function selectPackage(float $volume, int $weight): array
    {
        $validCodes = array_keys($this->options->packageSizes());
        $packages = $this->settings->get('shipping.packages', []);
        if (! is_array($packages)) {
            throw new ShippingConfigurationException('تنظیمات بسته‌بندی نامعتبر است.');
        }

        $fits = array_filter($packages, function (mixed $package) use ($validCodes, $volume, $weight): bool {
            return is_array($package)
                && ($package['active'] ?? true)
                && isset($package['id'], $package['name'], $package['capacity_volume'], $package['max_weight'], $package['code'])
                && in_array((int) $package['code'], $validCodes, true)
                && (float) $package['capacity_volume'] >= $volume
                && (float) $package['max_weight'] >= $weight;
        });
        if ($fits === []) {
            throw new ShippingConfigurationException('هیچ بسته‌بندی مناسبی برای این مرسوله وجود ندارد.');
        }

        usort($fits, fn (array $a, array $b): int => [(float) $a['capacity_volume'], (float) $a['max_weight'], (string) $a['id']]
            <=> [(float) $b['capacity_volume'], (float) $b['max_weight'], (string) $b['id']]);

        return ['code' => (int) $fits[0]['code'], 'id' => (string) $fits[0]['id'], 'name' => (string) $fits[0]['name'], 'capacity_volume' => (float) $fits[0]['capacity_volume'], 'max_weight' => (float) $fits[0]['max_weight']];
    }

    /** @param array{weight_grams: int, volume: float, parcel_type: string} $metrics */
    private function zero(string $service, string $mode, string $label, array $metrics): ShippingQuoteResult
    {
        return new ShippingQuoteResult($service, true, 0, 'IRR', [['key' => $mode, 'label' => $label, 'amount' => 0]], metadata: ['calculation_mode' => $mode, ...$metrics]);
    }
}
