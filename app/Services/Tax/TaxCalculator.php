<?php

namespace App\Services\Tax;

use App\Enums\TaxType;
use App\Models\TaxClass;
use InvalidArgumentException;

class TaxCalculator
{
    private const PERCENT_SCALE = 1_000;

    private const PERCENT_DENOMINATOR = 100 * self::PERCENT_SCALE;

    /**
     * Calculates tax on a tax-exclusive line amount expressed in Iranian Rial.
     *
     * A fixed class is a per-unit Rial amount, so callers must provide the line quantity.
     */
    public function calculateTax(int $taxableAmountRials, ?TaxClass $taxClass, int $quantity = 1): int
    {
        $this->assertNonNegative($taxableAmountRials, 'مبلغ مشمول مالیات');
        $this->assertPositive($quantity, 'تعداد');

        if ($taxClass === null || ! $taxClass->is_active) {
            return 0;
        }

        return match ($this->typeFor($taxClass)) {
            TaxType::Percent => $this->percentageTax($taxableAmountRials, $this->percentageThousandths($taxClass)),
            TaxType::Fixed => $this->fixedTax($taxClass, $quantity),
        };
    }

    public function calculateGross(int $taxableAmountRials, ?TaxClass $taxClass, int $quantity = 1): int
    {
        return $taxableAmountRials + $this->calculateTax($taxableAmountRials, $taxClass, $quantity);
    }

    private function percentageTax(int $taxableAmountRials, int $percentageThousandths): int
    {
        $wholeUnits = intdiv($taxableAmountRials, self::PERCENT_DENOMINATOR);
        $remainder = $taxableAmountRials % self::PERCENT_DENOMINATOR;

        // Half-up rounding to the nearest Rial, without a float intermediate.
        return ($wholeUnits * $percentageThousandths)
            + intdiv(
                ($remainder * $percentageThousandths) + intdiv(self::PERCENT_DENOMINATOR, 2),
                self::PERCENT_DENOMINATOR,
            );
    }

    private function fixedTax(TaxClass $taxClass, int $quantity): int
    {
        $amount = $this->fixedRialAmount($taxClass);

        if ($amount > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('مبلغ مالیات ثابت خارج از محدوده پشتیبانی‌شده است.');
        }

        return $amount * $quantity;
    }

    private function percentageThousandths(TaxClass $taxClass): int
    {
        [$whole, $fraction] = $this->decimalParts($taxClass->value, 'نرخ مالیات درصدی');
        $rate = ($whole * self::PERCENT_SCALE) + $fraction;

        if ($rate > self::PERCENT_DENOMINATOR) {
            throw new InvalidArgumentException('نرخ مالیات درصدی نمی‌تواند بیشتر از ۱۰۰ درصد باشد.');
        }

        return $rate;
    }

    private function fixedRialAmount(TaxClass $taxClass): int
    {
        [$whole, $fraction] = $this->decimalParts($taxClass->value, 'مبلغ مالیات ثابت');

        if ($fraction !== 0) {
            throw new InvalidArgumentException('مبلغ مالیات ثابت باید یک عدد صحیح بر حسب ریال باشد.');
        }

        return $whole;
    }

    /** @return array{0: int, 1: int} */
    private function decimalParts(mixed $value, string $label): array
    {
        $value = trim((string) $value);

        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("{$label} باید یک عدد غیرمنفی با حداکثر سه رقم اعشار باشد.");
        }

        $whole = (int) $matches[1];

        if ((string) $whole !== ltrim($matches[1], '0') && $matches[1] !== '0') {
            throw new InvalidArgumentException("{$label} خارج از محدوده پشتیبانی‌شده است.");
        }

        $fraction = isset($matches[2])
            ? (int) str_pad($matches[2], 3, '0')
            : 0;

        return [$whole, $fraction];
    }

    private function typeFor(TaxClass $taxClass): TaxType
    {
        $type = TaxType::parse($taxClass->type);

        if ($type === null) {
            throw new InvalidArgumentException('نوع کلاس مالیاتی نامعتبر است.');
        }

        return $type;
    }

    private function assertNonNegative(int $value, string $label): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$label} نمی‌تواند منفی باشد.");
        }
    }

    private function assertPositive(int $value, string $label): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException("{$label} باید حداقل یک باشد.");
        }
    }
}
