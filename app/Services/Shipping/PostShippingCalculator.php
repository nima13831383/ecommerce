<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\DTO\ShippingQuoteResult;

class PostShippingCalculator
{
    private const ISLAND_CITY_IDS = [79351, 7951, 75461, 79551, 7941, 79591, 79781];

    private const MAX_DECLARED_VALUE_RIALS = 1_000_000_000;

    private const EMBEDDED_COMPENSATION_RIALS = 100_000;

    /**
     * Current public Tapin size adjustments. The percentage is applied to the
     * postal rate after excluding its embedded 100,000-rial compensation.
     */
    private const PACKAGE_SURCHARGE_RATES = [
        1 => 0.00,
        2 => 0.00,
        3 => 0.00,
        4 => 0.10,
        5 => 0.15,
        6 => 0.20,
        7 => 0.25,
        8 => 0.35,
        9 => 0.45,
        10 => 0.60,
    ];

    /** @var array<string, array<string, array{first_kg: int, additional_kg: int}>> */
    private const SERVICE_RATES = [
        'pishtaz' => [
            'in' => ['first_kg' => 800_000, 'additional_kg' => 125_000],
            'beside' => ['first_kg' => 900_000, 'additional_kg' => 155_000],
            'out' => ['first_kg' => 1_000_000, 'additional_kg' => 175_000],
        ],
        'vijeh' => [
            'in' => ['first_kg' => 800_000, 'additional_kg' => 150_000],
            'beside' => ['first_kg' => 1_100_000, 'additional_kg' => 165_000],
            'out' => ['first_kg' => 1_550_000, 'additional_kg' => 200_000],
        ],
    ];

    public function __construct(
        private readonly WordpressShippingDataLoader $dataLoader,
        private readonly int $tapinServiceFeeRials = 30_000,
        private readonly int $postalServiceFeeRials = 35_000,
    ) {}

    public function calculate(ShippingQuoteInput $input): ShippingQuoteResult
    {
        $availability = $input->service === 'pishtaz' ? true : null;
        $warnings = $this->availabilityWarnings($input);

        if ($input->paymentType === 'free') {
            return $this->zeroQuote($input, $availability, $warnings, 'free_shipping', 'اعمال ارسال رایگان');
        }

        if ($input->paymentType === 'postpaid') {
            return $this->zeroQuote($input, $availability, $warnings, 'postpaid', 'هزینه نمایشی فروشگاه در حالت پس‌کرایه');
        }

        $weightInWholeGrams = (int) ceil($input->weightGrams);
        $weightBracket = max(1_000, min(30_000, (int) ceil($weightInWholeGrams / 1_000) * 1_000));
        $additionalKilograms = max(0, ($weightBracket / 1_000) - 1);
        $effectivePackageSize = max(1, min(10, $input->packageSizeId));
        $zone = $this->destinationZone($input);
        $serviceRate = self::SERVICE_RATES[$input->service][$zone];

        $basePostage = $serviceRate['first_kg'] + ($additionalKilograms * $serviceRate['additional_kg']);
        $postage = $basePostage;
        $breakdown = [
            $this->component('base_postage', 'نرخ پایه تعرفه پست', $basePostage),
        ];

        $packageSurcharge = ($basePostage - self::EMBEDDED_COMPENSATION_RIALS)
            * self::PACKAGE_SURCHARGE_RATES[$effectivePackageSize];
        if ($packageSurcharge > 0) {
            $postage += $packageSurcharge;
            $breakdown[] = $this->component('package_surcharge', 'هزینه اندازه بسته', $packageSurcharge);
        }

        if (in_array($input->destinationCityId, self::ISLAND_CITY_IDS, true)) {
            $amount = 255_000 * ($weightBracket / 1_000);
            $postage += $amount;
            $breakdown[] = $this->component('island_surcharge', 'اضافه‌نرخ مقصد جزیره‌ای', $amount);
        }

        if ($input->parcelType === 'fragile_liquid') {
            $amount = ($postage - self::EMBEDDED_COMPENSATION_RIALS) * 0.25;
            $postage += $amount;
            $breakdown[] = $this->component('fragile_liquid_surcharge', 'اضافه‌نرخ شکستنی یا مایعات (۲۵٪)', $amount);
        }

        $declaredValueUsed = min($input->declaredValueRials, self::MAX_DECLARED_VALUE_RIALS);
        $declaredCompensation = $this->declaredCompensation($declaredValueUsed);
        $declaredValueAdjustment = $declaredCompensation - self::EMBEDDED_COMPENSATION_RIALS;
        if ($declaredValueAdjustment > 0) {
            $postage += $declaredValueAdjustment;
            $breakdown[] = $this->component('declared_value_adjustment', 'تعدیل تعهد غرامت ارزش اظهارشده', $declaredValueAdjustment);
        }

        if ($input->paymentType === 'cod') {
            $cod = $this->cashOnDeliveryCost($declaredValueUsed);
            $postage += $cod;
            $breakdown[] = $this->component('cod', 'هزینه پرداخت در محل', $cod);
        }

        $postage = (int) $postage;
        $postageTax = (int) ($postage * 0.10);
        $breakdown[] = $this->component('postage_tax', 'مالیات ارزش افزوده هزینه پست', $postageTax);

        $tapinServiceTax = (int) ($this->tapinServiceFeeRials * 0.10);
        $postalServiceTax = (int) ($this->postalServiceFeeRials * 0.10);
        $serviceTotal = $this->tapinServiceFeeRials
            + $tapinServiceTax
            + $this->postalServiceFeeRials
            + $postalServiceTax;

        $breakdown[] = $this->component('service_total', 'مجموع هزینه خدمات (شامل مالیات)', $serviceTotal);

        // Tapin public calculator v2.5.0 adds service_price + its VAT once more
        // after returning total_service_price. Preserve this observable total.
        $tapinTotalAdjustment = $this->tapinServiceFeeRials + $tapinServiceTax;
        $breakdown[] = $this->component(
            'tapin_total_adjustment',
            'تعدیل جمع کل مرجع تاپین (هزینه خدمت و مالیات آن)',
            $tapinTotalAdjustment,
        );

        $total = $postage + $postageTax + $serviceTotal + $tapinTotalAdjustment;

        if ($declaredValueUsed !== $input->declaredValueRials) {
            $warnings[] = 'ارزش مرسوله برای محاسبه به حداکثر یک میلیارد ریال محدود شد.';
        }

        if ($effectivePackageSize !== $input->packageSizeId) {
            $warnings[] = "اندازه بسته {$input->packageSizeId} برای نرخ‌گذاری به اندازه ۱۰ تبدیل شد.";
        }

        return new ShippingQuoteResult(
            service: $input->service,
            available: $availability,
            total: $total,
            currency: 'IRR',
            breakdown: $breakdown,
            warnings: $warnings,
            metadata: [
                'calculation_mode' => 'tapin_public_tariff_1405',
                'rate_source' => 'نرخ‌نامه ۱۴۰۵ + مرجع عمومی تاپین 2.5.0',
                'plugin_rate_reference' => $this->dataLoader->rateTableRelativePath($input->service, $input->originProvinceId),
                'weight_input_grams' => $input->weightGrams,
                'weight_whole_grams' => $weightInWholeGrams,
                'weight_bracket_grams' => $weightBracket,
                'destination_zone' => $zone,
                'package_size_selected' => $input->packageSizeId,
                'package_size_effective' => $effectivePackageSize,
                'declared_value_input_rials' => $input->declaredValueRials,
                'declared_value_used_rials' => $declaredValueUsed,
                'postage_before_tax_rials' => $postage,
                'postage_tax_rials' => $postageTax,
                'service_total_rials' => $serviceTotal,
                'tapin_total_adjustment_rials' => $tapinTotalAdjustment,
                'availability_verified' => $availability !== null,
            ],
        );
    }

    private function destinationZone(ShippingQuoteInput $input): string
    {
        if ($input->originProvinceId === $input->destinationProvinceId) {
            return 'in';
        }

        return $this->dataLoader->areNeighboringProvinces(
            $input->originProvinceId,
            $input->destinationProvinceId,
        ) ? 'beside' : 'out';
    }

    private function declaredCompensation(int $declaredValueRials): int
    {
        return match (true) {
            $declaredValueRials <= 60_000_000 => self::EMBEDDED_COMPENSATION_RIALS,
            $declaredValueRials < 300_000_000 => (int) ($declaredValueRials * 0.002) + 143,
            $declaredValueRials < 500_000_000 => (int) ($declaredValueRials * 0.0025) + 179,
            $declaredValueRials < 1_000_000_000 => (int) ($declaredValueRials * 0.003) + 214,
            default => (int) ($declaredValueRials * 0.0035) + 250,
        };
    }

    private function cashOnDeliveryCost(int $declaredValueRials): int
    {
        return match (true) {
            $declaredValueRials >= 500_000_000 => 450_000,
            $declaredValueRials >= 200_000_000 => 350_000,
            $declaredValueRials >= 100_000_000 => 300_000,
            $declaredValueRials >= 50_000_000 => 150_000,
            $declaredValueRials >= 10_000_000 => 150_000,
            default => (int) ($declaredValueRials * 0.01),
        };
    }

    /** @return array{key: string, label: string, amount: int|float} */
    private function component(string $key, string $label, int|float $amount): array
    {
        return compact('key', 'label', 'amount');
    }

    /** @param array<int, string> $warnings */
    private function zeroQuote(
        ShippingQuoteInput $input,
        ?bool $availability,
        array $warnings,
        string $mode,
        string $label,
    ): ShippingQuoteResult {
        return new ShippingQuoteResult(
            service: $input->service,
            available: $availability,
            total: 0,
            currency: 'IRR',
            breakdown: [$this->component($mode, $label, 0)],
            warnings: $warnings,
            metadata: ['calculation_mode' => $mode],
        );
    }

    /** @return array<int, string> */
    private function availabilityWarnings(ShippingQuoteInput $input): array
    {
        if ($input->service !== 'vijeh') {
            return [];
        }

        return [
            'دسترسی عملیاتی پست ویژه به ماتریس سرویس شهرهای API تاپین وابسته است؛ قیمت محاسبه می‌شود اما دسترسی نیازمند تأیید پنل تاپین است.',
        ];
    }
}
